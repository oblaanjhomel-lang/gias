<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WeeklyReportStatus;
use App\Models\Deployment;
use App\Models\User;
use App\Models\WeeklyReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/dashboard: numbers only, per role. The frontend writes the sentences.
 */
class DashboardService
{
    /** Open slots of a partner row aliased "ip" (never negative). */
    private const OPEN_SLOTS_SQL = "greatest(ip.intern_slots - (select count(*) from deployments x where x.industry_partner_id = ip.id and x.status = 'active'), 0)";

    public function __construct(private readonly DeploymentMonitoringService $monitoring) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return match ($user->role) {
            UserRole::Admin => $this->admin(),
            UserRole::Coordinator => $this->coordinator($user),
            UserRole::Partner => $this->partner($user),
            UserRole::Student => $this->student($user),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $partnersByStatus = DB::table('users')->where('role', UserRole::Partner->value)
            ->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status');

        $active = DB::table('deployments as d')
            ->join('industry_partners as ip', 'ip.id', '=', 'd.industry_partner_id')
            ->where('d.status', DeploymentStatus::Active->value)
            ->selectRaw('count(*) as deployments, count(distinct ip.province) as provinces')
            ->first();

        $unlocated = DB::table('industry_partners as ip')->join('users as u', 'u.id', '=', 'ip.user_id')
            ->whereNull('ip.location')
            ->whereIn('u.status', [UserStatus::Active->value, UserStatus::Pending->value])
            ->count();

        $withoutCoordinator = DB::table('programs as p')
            ->whereNotExists(fn (QueryBuilder $q) => $q->from('users as c')->whereColumn('c.program_id', 'p.id')
                ->where('c.role', UserRole::Coordinator->value)->where('c.status', UserStatus::Active->value))
            ->orderBy('p.code')->pluck('p.code')->all();

        $awaitingReview = DB::table('evaluations')->where('status', 'submitted')->count();

        $saturation = $this->activePartners()
            ->where('ip.intern_slots', '>', 0)
            ->selectRaw("ip.company_name as company, ip.city, ip.intern_slots as slots, (select count(*) from deployments x where x.industry_partner_id = ip.id and x.status = 'active') as filled")
            ->orderByRaw("(select count(*) from deployments x where x.industry_partner_id = ip.id and x.status = 'active')::float / ip.intern_slots desc")
            ->orderBy('ip.company_name')
            ->limit(5)
            ->get()
            ->map(fn (object $r) => [
                'company' => $r->company,
                'city' => $r->city,
                'filled' => (int) $r->filled,
                'slots' => (int) $r->slots,
                'open' => max(0, (int) $r->slots - (int) $r->filled),
            ])->all();

        return [
            'role' => 'admin',
            'stats' => [
                'active_partners' => (int) ($partnersByStatus[UserStatus::Active->value] ?? 0),
                'pending_partners' => (int) ($partnersByStatus[UserStatus::Pending->value] ?? 0),
                'active_deployments' => (int) $active->deployments,
                'provinces_covered' => (int) $active->provinces,
                'unlocated_partners' => $unlocated,
                'programs_without_coordinator' => $withoutCoordinator,
                'evaluations_awaiting_review' => $awaitingReview,
            ],
            'saturation_top' => $saturation,
            'queue' => [
                'pending_partner_approvals' => (int) ($partnersByStatus[UserStatus::Pending->value] ?? 0),
                'unlocated_partners' => $unlocated,
                'programs_without_coordinator' => count($withoutCoordinator),
                'evaluations_awaiting_review' => $awaitingReview,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coordinator(User $user): array
    {
        $programId = $user->program_id ?? 0;
        $scopeIds = $this->monitoring->inScope($user)->select('deployments.id');

        $pending = DB::table('users')->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Pending->value)->where('program_id', $programId)->count();

        $openSlots = (int) $this->activePartners()->sum(DB::raw(self::OPEN_SLOTS_SQL));

        $overdueByPartner = $this->monitoring->whereOverdue($this->monitoring->inScope($user))
            ->pluck('industry_partner_id')->countBy();

        $unplaced = DB::table('students as s')->join('users as u', 'u.id', '=', 's.user_id')
            ->where('u.role', UserRole::Student->value)->where('u.status', UserStatus::Active->value)
            ->where('u.program_id', $programId)
            ->whereNotExists(fn (QueryBuilder $q) => $q->from('deployments as d')->whereColumn('d.student_id', 's.id')
                ->whereIn('d.status', [DeploymentStatus::Active->value, DeploymentStatus::Completed->value]))
            ->count();

        $byPartner = DB::table('deployments as d')
            ->join('industry_partners as ip', 'ip.id', '=', 'd.industry_partner_id')
            ->whereIn('d.id', $scopeIds)
            ->where('d.status', DeploymentStatus::Active->value)
            ->groupBy('ip.id', 'ip.company_name', 'ip.city', 'ip.intern_slots')
            ->selectRaw('ip.id, ip.company_name as company, ip.city, count(*) as interns, '.self::OPEN_SLOTS_SQL.' as open')
            ->orderByDesc('interns')->orderBy('ip.company_name')
            ->get();

        $rows = $byPartner->map(fn (object $r) => [
            'company' => $r->company,
            'city' => $r->city,
            'interns' => (int) $r->interns,
            'open' => (int) $r->open,
            'overdue' => (int) ($overdueByPartner[$r->id] ?? 0),
            'unplaced' => false,
        ])->all();
        $rows[] = ['company' => null, 'city' => null, 'interns' => $unplaced, 'open' => null, 'overdue' => null, 'unplaced' => true];

        $toReview = DB::table('evaluations')->where('status', 'submitted')->whereIn('deployment_id', $scopeIds)->count();
        $activeInterns = (int) $byPartner->sum('interns');
        $overdue = $overdueByPartner->sum();

        return [
            'role' => 'coordinator',
            'stats' => [
                'active_interns' => $activeInterns,
                'pending_students' => $pending,
                'open_slots' => $openSlots,
                'overdue_interns' => $overdue,
                'unplaced_students' => $unplaced,
            ],
            'interns_by_partner' => $rows,
            'queue' => [
                'pending_students' => $pending,
                'overdue_interns' => $overdue,
                'unplaced_students' => $unplaced,
                'evaluations_to_review' => $toReview,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partner(User $user): array
    {
        $profile = $user->industryPartner;

        $deployments = $profile
            ? $this->monitoring->withProgress($profile->deployments()->where('status', DeploymentStatus::Active)->getQuery())
                ->with(['student.user.program', 'evaluations'])
                ->get()
            : collect();

        $evaluationsOpen = 0;
        $interns = [];
        foreach ($deployments as $deployment) {
            /** @var Deployment $deployment */
            $progress = $this->monitoring->progress($deployment);
            foreach (EvaluationType::cases() as $type) {
                $threshold = config("gias.evaluation.opens_at_percent.{$type->value}") * $progress['required_hours'] / 100;
                $done = $deployment->evaluations->contains(fn ($e) => $e->type === $type && in_array($e->status->value, ['submitted', 'reviewed'], true));
                if ($progress['signed_hours'] >= round($threshold, 2) && ! $done) {
                    $evaluationsOpen++;
                }
            }
            $interns[] = [
                'name' => $deployment->student->user->name,
                'program' => $deployment->student->user->program?->code,
                'signed_hours' => $progress['signed_hours'],
                'required_hours' => $progress['required_hours'],
                'last_report' => $progress['last_report_period_end'],
            ];
        }
        usort($interns, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        $toSign = $profile ? WeeklyReport::query()
            ->where('status', WeeklyReportStatus::Submitted)
            ->whereIn('deployment_id', $profile->deployments()->select('id'))
            ->count() : 0;

        $programs = collect($interns)->pluck('program')->filter()->unique()->sort()->values()->all();

        return [
            'role' => 'partner',
            'stats' => [
                'interns_hosted' => $deployments->count(),
                'slots' => (int) ($profile->intern_slots ?? 0),
                'reports_to_sign' => $toSign,
                'evaluations_open' => $evaluationsOpen,
                'programs_served' => $programs,
            ],
            'interns' => array_slice($interns, 0, 5),
            'queue' => [
                'reports_to_sign' => $toSign,
                'evaluations_open' => $evaluationsOpen,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function student(User $user): array
    {
        $deployment = $user->student?->deployments()
            ->whereIn('status', [DeploymentStatus::Active, DeploymentStatus::Completed])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('start_date')
            ->with(['industryPartner', 'weeklyReports' => fn ($q) => $q->orderBy('week_number'), 'final'])
            ->first();

        if (! $deployment) {
            return [
                'role' => 'student',
                'stats' => null,
                'weeks_remaining_estimate' => null,
                'placement' => null,
                'next_steps' => [['type' => 'not_placed']],
            ];
        }

        $progress = $this->monitoring->progress($deployment);
        $reports = $deployment->weeklyReports;
        $lastReport = $reports->where('status', '!=', WeeklyReportStatus::Draft)->sortByDesc('week_number')->first();

        $signedWeeks = $reports->where('status', WeeklyReportStatus::Signed)->count();
        $remaining = max(0, $progress['required_hours'] - $progress['signed_hours']);
        $weeksRemaining = $signedWeeks > 0
            ? (int) ceil($remaining / max(1, $progress['signed_hours'] / $signedWeeks))
            : null;

        $nextReport = $deployment->status === DeploymentStatus::Active ? $this->nextReport($deployment) : null;

        $steps = [];
        if ($nextReport) {
            $steps[] = ['type' => 'report_due'] + $nextReport;
        }
        foreach ($reports->where('status', WeeklyReportStatus::Returned) as $returned) {
            $steps[] = ['type' => 'report_returned', 'week' => $returned->week_number, 'date' => $returned->period_end->toDateString()];
        }
        if ($deployment->final?->status->value === 'reviewed') {
            $steps[] = ['type' => 'evaluation_released', 'date' => $deployment->final->reviewed_at?->toDateString()];
        }

        $coordinator = User::query()->where('role', UserRole::Coordinator)->where('status', UserStatus::Active)
            ->where('program_id', $user->program_id)->value('name');

        return [
            'role' => 'student',
            'stats' => [
                'signed_hours' => $progress['signed_hours'],
                'required_hours' => $progress['required_hours'],
                'pending_hours' => $progress['pending_hours'],
                'deployment_status' => $deployment->status->value,
                'partner' => ['company' => $deployment->industryPartner->company_name, 'city' => $deployment->industryPartner->city],
                'last_report_period_end' => $lastReport?->period_end->toDateString(),
                'last_report_status' => $lastReport?->status->value,
            ],
            'weeks_remaining_estimate' => $weeksRemaining,
            'placement' => [
                'company' => $deployment->industryPartner->company_name,
                'city' => $deployment->industryPartner->city,
                'supervisor' => $deployment->industryPartner->contact_person,
                'coordinator' => $coordinator,
                'start_date' => $deployment->start_date->toDateString(),
                'next_report' => $nextReport,
            ],
            'next_steps' => $steps,
        ];
    }

    /**
     * The earliest finished week without a submitted/signed report; otherwise the week in
     * progress (due at its period end).
     *
     * @return array{week: int, date: string}|null
     */
    private function nextReport(Deployment $deployment): ?array
    {
        $filed = $deployment->weeklyReports
            ->whereIn('status', [WeeklyReportStatus::Submitted, WeeklyReportStatus::Signed])
            ->pluck('week_number')->all();
        $today = WeeklyReportService::today();

        for ($week = 1; $week <= 104; $week++) {
            [$start, $end] = WeeklyReportService::periodFor($deployment->start_date, $week);
            if ($start->toDateString() > $today) {
                return null;
            }
            if (! in_array($week, $filed, true)) {
                return ['week' => $week, 'date' => $end->toDateString()];
            }
        }

        return null;
    }

    private function activePartners(): QueryBuilder
    {
        return DB::table('industry_partners as ip')
            ->join('users as pu', 'pu.id', '=', 'ip.user_id')
            ->where('pu.status', UserStatus::Active->value);
    }
}
