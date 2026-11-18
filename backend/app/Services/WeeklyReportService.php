<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\WeeklyReportStatus;
use App\Events\WeeklyReportReturned;
use App\Events\WeeklyReportSigned;
use App\Events\WeeklyReportSubmitted;
use App\Models\Deployment;
use App\Models\User;
use App\Models\WeeklyReport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Weekly OJT reports. Ownership is checked by WeeklyReportPolicy on the routes; this
 * service enforces the status flow (422):
 *   draft → submitted (student) → signed (partner, final) | returned (partner, with note)
 *   returned → submitted again after the student edits it.
 * Only draft / returned reports can be edited or deleted.
 *
 * Week n covers start_date + 7·(n−1) through +6 days, ALWAYS computed here.
 */
class WeeklyReportService
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} period start and end (inclusive)
     */
    public static function periodFor(CarbonInterface|string $startDate, int $week): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay()->addDays(7 * ($week - 1));

        return [$start, $start->addDays(6)];
    }

    /** Today's date (Y-m-d) on the local (Philippine) calendar. */
    public static function today(): string
    {
        return CarbonImmutable::now(config('gias.local_timezone'))->toDateString();
    }

    /**
     * Weeks that have started for an active deployment, with any existing report.
     *
     * @return list<array{week_number: int, period_start: string, period_end: string, report_id: int|null, status: string|null}>
     */
    public function availableWeeks(Deployment $deployment): array
    {
        if ($deployment->status !== DeploymentStatus::Active) {
            return [];
        }

        $reports = $deployment->weeklyReports->keyBy('week_number');
        $weeks = [];
        for ($n = 1; $n <= 104; $n++) {
            [$start, $end] = self::periodFor($deployment->start_date, $n);
            if ($start->toDateString() > self::today()) {
                break;
            }
            $report = $reports->get($n);
            $weeks[] = [
                'week_number' => $n,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'report_id' => $report?->id,
                'status' => $report?->status->value,
            ];
        }

        return $weeks;
    }

    // Student

    /**
     * Create the week's report as a draft, or update it if it exists and is still editable.
     *
     * @param  array{week_number: int, daily_hours: array<string, float>, tasks: string, learnings?: string|null}  $data
     */
    public function saveDraft(User $studentUser, array $data): WeeklyReport
    {
        $deployment = $this->activeDeploymentOf($studentUser);
        $week = (int) $data['week_number'];
        [$start, $end] = self::periodFor($deployment->start_date, $week);

        if ($start->toDateString() > self::today()) {
            $this->fail('week_number', "Week {$week} starts on {$start->format('j M Y')}. You can report on it from then.");
        }

        return DB::transaction(function () use ($deployment, $week, $start, $end, $data) {
            $report = WeeklyReport::query()
                ->where('deployment_id', $deployment->id)
                ->where('week_number', $week)
                ->lockForUpdate()
                ->first();

            if ($report && ! $report->status->isEditable()) {
                $this->fail('week_number', "Week {$week} is already {$report->status->value} and can’t be edited.");
            }

            $report ??= new WeeklyReport([
                'deployment_id' => $deployment->id,
                'week_number' => $week,
                'status' => WeeklyReportStatus::Draft,
            ]);
            $report->fill(['period_start' => $start, 'period_end' => $end] + $this->content($data))->save();

            return $report;
        });
    }

    /**
     * @param  array{daily_hours: array<string, float>, tasks: string, learnings?: string|null}  $data
     */
    public function update(User $studentUser, WeeklyReport $report, array $data): WeeklyReport
    {
        return DB::transaction(function () use ($report, $data) {
            $report = $this->lock($report);
            $this->ensureEditable($report, 'edited');
            $this->ensureDeploymentActive($report);

            $report->fill($this->content($data))->save();

            return $report;
        });
    }

    public function submit(User $studentUser, WeeklyReport $report): WeeklyReport
    {
        return DB::transaction(function () use ($report) {
            $report = $this->lock($report);
            $this->ensureEditable($report, 'submitted');
            $this->ensureDeploymentActive($report);

            $report->update(['status' => WeeklyReportStatus::Submitted, 'submitted_at' => now()]);
            WeeklyReportSubmitted::dispatch($report); // after commit

            return $report;
        });
    }

    public function delete(User $studentUser, WeeklyReport $report): void
    {
        DB::transaction(function () use ($report) {
            $report = $this->lock($report);
            $this->ensureEditable($report, 'deleted');
            $report->delete();
        });
    }

    // Industry partner

    public function sign(User $partnerUser, WeeklyReport $report): WeeklyReport
    {
        return DB::transaction(function () use ($partnerUser, $report) {
            $report = $this->lock($report);
            $this->ensureSubmitted($report, 'countersigned');

            $report->update([
                'status' => WeeklyReportStatus::Signed,
                'signed_by' => $partnerUser->id,
                'signed_at' => now(),
            ]);
            WeeklyReportSigned::dispatch($report);

            return $report;
        });
    }

    public function return(User $partnerUser, WeeklyReport $report, string $note): WeeklyReport
    {
        return DB::transaction(function () use ($report, $note) {
            $report = $this->lock($report);
            $this->ensureSubmitted($report, 'returned');

            $report->update(['status' => WeeklyReportStatus::Returned, 'return_note' => $note]);
            WeeklyReportReturned::dispatch($report);

            return $report;
        });
    }

    /**
     * Reports from deployments at the partner's site, newest work first; plus counts per status.
     *
     * @param  array{status?: string|null}  $filters
     * @return array{reports: LengthAwarePaginator<int, WeeklyReport>, counts: array<string, int>}
     */
    public function forPartner(User $partnerUser, array $filters): array
    {
        $profileId = $partnerUser->industryPartner()->value('id');
        $status = $filters['status'] ?? WeeklyReportStatus::Submitted->value;

        $atSite = fn () => WeeklyReport::query()
            ->whereHas('deployment', fn (Builder $q) => $q->where('industry_partner_id', $profileId));

        $reports = $atSite()
            ->where('status', $status)
            ->with(['deployment.student.user.program', 'signedBy'])
            ->when(
                $status === WeeklyReportStatus::Submitted->value,
                fn (Builder $q) => $q->orderBy('submitted_at'), // oldest waiting first
                fn (Builder $q) => $q->orderByDesc('updated_at'),
            )
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $counts = $atSite()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        return [
            'reports' => $reports,
            'counts' => [
                'submitted' => $counts['submitted'] ?? 0,
                'signed' => $counts['signed'] ?? 0,
                'returned' => $counts['returned'] ?? 0,
            ],
        ];
    }

    public function forPartnerShow(WeeklyReport $report): WeeklyReport
    {
        return $report->load(['deployment.student.user.program', 'signedBy']);
    }

    // Helpers

    /**
     * @param  array<string, mixed>  $data
     * @return array{daily_hours: array<string, float>, total_hours: float, tasks: string, learnings: string|null}
     */
    private function content(array $data): array
    {
        $hours = [];
        foreach (WeeklyReport::DAYS as $day) {
            $hours[$day] = round((float) ($data['daily_hours'][$day] ?? 0), 1);
        }

        return [
            'daily_hours' => $hours,
            'total_hours' => array_sum($hours), // server-side, never trusted from the client
            'tasks' => $data['tasks'],
            'learnings' => $data['learnings'] ?? null,
        ];
    }

    private function activeDeploymentOf(User $studentUser): Deployment
    {
        $deployment = $studentUser->student?->activeDeployment;

        if (! $deployment) {
            $this->fail('deployment', 'You need an active deployment to write weekly reports.');
        }

        return $deployment;
    }

    private function lock(WeeklyReport $report): WeeklyReport
    {
        return WeeklyReport::query()->with('deployment')->lockForUpdate()->findOrFail($report->id);
    }

    private function ensureEditable(WeeklyReport $report, string $action): void
    {
        if (! $report->status->isEditable()) {
            $this->fail('status', "This report is {$report->status->value}; only draft or returned reports can be {$action}.");
        }
    }

    private function ensureSubmitted(WeeklyReport $report, string $action): void
    {
        if ($report->status !== WeeklyReportStatus::Submitted) {
            $this->fail('status', "Only submitted reports can be {$action}; this one is {$report->status->value}.");
        }
    }

    private function ensureDeploymentActive(WeeklyReport $report): void
    {
        if ($report->deployment->status !== DeploymentStatus::Active) {
            $this->fail('deployment', 'This deployment is no longer active.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
