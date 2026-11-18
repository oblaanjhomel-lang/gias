<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\EvaluationType;
use App\Enums\UserStatus;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Notifications\EvaluationsAwaitingReviewReminder;
use App\Notifications\EvaluationWindowOpened;
use App\Notifications\GiasNotification;
use App\Notifications\OverdueInternsDigest;
use App\Notifications\ReportOverdueReminder;
use App\Notifications\ReportsAwaitingSignatureReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled reminders (gias:send-reminders, daily 08:00 Manila). Each reminder is sent once
 * per window: its dedupe key is inserted into notification_dedupes first, and a key that
 * already exists means "already sent in this window".
 *
 *  overdue:{deployment}:{iso-week}       overdue student (Module 6 rule), once per ISO week
 *  sign:{partner}:{3-day bucket}         partner with submitted reports older than 3 days
 *  evalopen:{deployment}:{type}          evaluation window open, not started or still a draft (once)
 *  review:{reviewer}:{3-day bucket}      coordinator / admin with submitted evaluations older than 3 days
 *  digest:{coordinator}:{iso-week}       Mondays: coordinator's overdue interns
 */
class ReminderService
{
    public function __construct(
        private readonly DeploymentMonitoringService $monitoring,
        private readonly EvaluationService $evaluations,
        private readonly RecipientService $recipients,
    ) {}

    /**
     * @return array<string, int> reminders sent per type
     */
    public function run(): array
    {
        $now = CarbonImmutable::now(config('gias.local_timezone'));

        return [
            'overdue_reports' => $this->overdueStudents($now),
            'reports_awaiting_signature' => $this->reportsAwaitingSignature($now),
            'evaluation_windows_opened' => $this->evaluationWindows(),
            'evaluations_awaiting_review' => $this->evaluationsAwaitingReview($now),
            'overdue_interns_digest' => $now->isMonday() ? $this->overdueDigests($now) : 0,
        ];
    }

    private function overdueStudents(CarbonImmutable $now): int
    {
        $sent = 0;
        $deployments = $this->monitoring->whereOverdue(Deployment::query())->with('student.user')->get();

        foreach ($deployments as $deployment) {
            $student = $deployment->student->user;
            $key = "overdue:{$deployment->id}:{$this->isoWeek($now)}";
            $sent += (int) $this->sendOnce($key, $student, new ReportOverdueReminder($deployment->id));
        }

        return $sent;
    }

    private function reportsAwaitingSignature(CarbonImmutable $now): int
    {
        $sent = 0;
        $perPartner = WeeklyReport::query()
            ->join('deployments', 'deployments.id', '=', 'weekly_reports.deployment_id')
            ->join('industry_partners', 'industry_partners.id', '=', 'deployments.industry_partner_id')
            ->where('weekly_reports.status', 'submitted')
            ->where('weekly_reports.submitted_at', '<', now()->subDays(3))
            ->groupBy('industry_partners.user_id')
            ->selectRaw('industry_partners.user_id, count(*) as total')
            ->pluck('total', 'user_id');

        foreach ($perPartner as $userId => $count) {
            $key = "sign:{$userId}:{$this->threeDayBucket($now)}";
            $sent += (int) $this->sendOnce($key, User::find($userId), new ReportsAwaitingSignatureReminder((int) $count));
        }

        return $sent;
    }

    private function evaluationWindows(): int
    {
        $sent = 0;
        $deployments = $this->monitoring->withProgress(Deployment::query()->where('status', DeploymentStatus::Active))
            ->with(['student.user.program', 'industryPartner.user', 'evaluations'])
            ->get();

        foreach ($deployments as $deployment) {
            foreach (EvaluationType::cases() as $type) {
                $evaluation = $deployment->evaluations->firstWhere('type', $type);
                $pending = $evaluation === null || $evaluation->status === EvaluationStatus::Draft;

                if ($pending && $this->evaluations->openState($deployment, $type)['open']) {
                    $key = "evalopen:{$deployment->id}:{$type->value}";
                    $notification = new EvaluationWindowOpened($deployment->student->user->name, $type->value, $deployment->id);
                    $sent += (int) $this->sendOnce($key, $deployment->industryPartner->user, $notification);
                }
            }
        }

        return $sent;
    }

    private function evaluationsAwaitingReview(CarbonImmutable $now): int
    {
        $perProgram = Evaluation::query()
            ->join('deployments', 'deployments.id', '=', 'evaluations.deployment_id')
            ->join('students', 'students.id', '=', 'deployments.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('evaluations.status', 'submitted')
            ->where('evaluations.submitted_at', '<', now()->subDays(3))
            ->groupBy('users.program_id')
            ->selectRaw('users.program_id, count(*) as total')
            ->pluck('total', 'program_id');

        // A program without a coordinator falls to the admins, who may collect several programs.
        $perReviewer = [];
        foreach ($perProgram as $programId => $count) {
            foreach ($this->recipients->programReviewers($programId ?: null) as $reviewer) {
                $perReviewer[$reviewer->id] = [
                    'user' => $reviewer,
                    'count' => ($perReviewer[$reviewer->id]['count'] ?? 0) + (int) $count,
                ];
            }
        }

        $sent = 0;
        foreach ($perReviewer as $id => ['user' => $reviewer, 'count' => $count]) {
            $key = "review:{$id}:{$this->threeDayBucket($now)}";
            $notification = new EvaluationsAwaitingReviewReminder($count, $this->recipients->reviewPath($reviewer, 'evaluations'));
            $sent += (int) $this->sendOnce($key, $reviewer, $notification);
        }

        return $sent;
    }

    private function overdueDigests(CarbonImmutable $now): int
    {
        $names = [];
        $deployments = $this->monitoring->whereOverdue(Deployment::query())->with('student.user')->get();
        foreach ($deployments as $deployment) {
            $names[$deployment->student->user->program_id][] = $deployment->student->user->name;
        }

        $sent = 0;
        foreach ($names as $programId => $programNames) {
            $coordinator = $this->recipients->programCoordinator($programId ?: null);
            if (! $coordinator) {
                continue; // the digest is for coordinators
            }
            sort($programNames);
            $key = "digest:{$coordinator->id}:{$this->isoWeek($now)}";
            $sent += (int) $this->sendOnce($key, $coordinator, new OverdueInternsDigest($programNames));
        }

        return $sent;
    }

    /**
     * Records the key, then sends. False when the key exists (already sent in this window)
     * or the recipient isn't an active user.
     */
    private function sendOnce(string $key, ?User $recipient, GiasNotification $notification): bool
    {
        if (! $recipient || $recipient->status !== UserStatus::Active) {
            return false;
        }

        $inserted = DB::table('notification_dedupes')->insertOrIgnore(['key' => $key, 'sent_at' => now()]);
        if ($inserted === 0) {
            return false;
        }

        $recipient->notify($notification);

        return true;
    }

    /** e.g. "2026-W47" */
    private function isoWeek(CarbonImmutable $date): string
    {
        return $date->format('o-\WW');
    }

    /** Local calendar days since 1970-01-01, in blocks of 3. */
    private function threeDayBucket(CarbonImmutable $date): int
    {
        return intdiv(intdiv(CarbonImmutable::parse($date->toDateString(), 'UTC')->getTimestamp(), 86400), 3);
    }
}
