<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WeeklyReportStatus;
use App\Events\DeploymentCompleted;
use App\Models\Deployment;
use App\Models\User;
use App\Models\WeeklyReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * OJT progress and monitoring.
 *  - Progress counts SIGNED hours only; percent = min(100, signed / program.required_ojt_hours).
 *  - Overdue: an ACTIVE deployment whose latest submitted/signed report ended (or, with no such
 *    report, which started) MORE than gias.monitoring.overdue_days ago (local calendar).
 * Lists compute hours with withSum / withMax subqueries: no per-row queries.
 */
class DeploymentMonitoringService
{
    private const PER_PAGE = 15;

    public function __construct(private readonly WeeklyReportService $reports) {}

    /** Reference dates before this (Y-m-d, exclusive) are overdue. */
    public static function overdueCutoff(): string
    {
        return CarbonImmutable::parse(WeeklyReportService::today())
            ->subDays((int) config('gias.monitoring.overdue_days'))
            ->toDateString();
    }

    // Progress

    /**
     * Adds signed_hours, pending_hours and last_report_period_end as subqueries.
     *
     * @param  Builder<Deployment>  $query
     * @return Builder<Deployment>
     */
    public function withProgress(Builder $query): Builder
    {
        return $query
            ->withSum(['weeklyReports as signed_hours' => fn (Builder $q) => $q->where('status', WeeklyReportStatus::Signed)], 'total_hours')
            ->withSum(['weeklyReports as pending_hours' => fn (Builder $q) => $q->where('status', WeeklyReportStatus::Submitted)], 'total_hours')
            ->withMax(['weeklyReports as last_report_period_end' => fn (Builder $q) => $q->whereIn('status', [
                WeeklyReportStatus::Submitted, WeeklyReportStatus::Signed,
            ])], 'period_end');
    }

    /**
     * @return array{signed_hours: float, pending_hours: float, required_hours: int, missing_hours: float, percent: float, weeks_elapsed: int, last_report_period_end: string|null, is_overdue: bool, status: string}
     */
    public function progress(Deployment $deployment): array
    {
        if (! array_key_exists('signed_hours', $deployment->getAttributes())) {
            $deployment->loadSum(['weeklyReports as signed_hours' => fn ($q) => $q->where('status', WeeklyReportStatus::Signed)], 'total_hours')
                ->loadSum(['weeklyReports as pending_hours' => fn ($q) => $q->where('status', WeeklyReportStatus::Submitted)], 'total_hours')
                ->loadMax(['weeklyReports as last_report_period_end' => fn ($q) => $q->whereIn('status', [
                    WeeklyReportStatus::Submitted, WeeklyReportStatus::Signed,
                ])], 'period_end');
        }
        $deployment->loadMissing('student.user.program');

        $required = (int) ($deployment->student?->user?->program?->required_ojt_hours ?? 486);
        $signed = round((float) $deployment->signed_hours, 2);
        $last = $deployment->last_report_period_end
            ? CarbonImmutable::parse($deployment->last_report_period_end)->toDateString()
            : null;
        $start = $deployment->start_date->toDateString();

        $isOverdue = $deployment->status === DeploymentStatus::Active
            && ($last ?? $start) < self::overdueCutoff();

        $status = match (true) {
            $deployment->status === DeploymentStatus::Completed => 'completed',
            $deployment->status === DeploymentStatus::Withdrawn => 'withdrawn',
            $isOverdue => 'overdue',
            default => 'active',
        };

        // Weeks elapsed up to today (active) or until it ended.
        $until = $deployment->status === DeploymentStatus::Active
            ? WeeklyReportService::today()
            : ($deployment->completed_at?->toDateString() ?? $deployment->end_date?->toDateString() ?? WeeklyReportService::today());
        $days = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($until), false);

        return [
            'signed_hours' => $signed,
            'pending_hours' => round((float) $deployment->pending_hours, 2),
            'required_hours' => $required,
            'missing_hours' => round(max(0, $required - $signed), 2),
            'percent' => $required > 0 ? round(min(100, $signed / $required * 100), 1) : 0.0,
            'weeks_elapsed' => $days < 0 ? 0 : intdiv((int) $days, 7) + 1,
            'last_report_period_end' => $last,
            'is_overdue' => $isOverdue,
            'status' => $status,
        ];
    }

    // Coordinator / admin

    /**
     * @param  array{filter?: string|null, search?: string|null, program_id?: int|null, province?: string|null}  $filters
     * @return array{deployments: LengthAwarePaginator<int, Deployment>, scope_total: int}
     */
    public function list(User $viewer, array $filters): array
    {
        $isAdmin = $viewer->role === UserRole::Admin;
        $search = trim($filters['search'] ?? '');
        $like = '%'.addcslashes($search, '%_\\').'%';

        $query = $this->withProgress($this->inScope($viewer))
            ->with(['student.user.program', 'industryPartner'])
            ->when($isAdmin && ($filters['program_id'] ?? null), fn (Builder $q) => $q->whereHas(
                'student.user', fn (Builder $u) => $u->where('program_id', (int) $filters['program_id']),
            ))
            ->when($isAdmin && ($filters['province'] ?? null), fn (Builder $q) => $q->whereHas(
                'industryPartner', fn (Builder $p) => $p->where('province', $filters['province']),
            ))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereHas('student.user', fn (Builder $u) => $u->whereLike('name', $like))
                ->orWhereHas('student', fn (Builder $s) => $s->whereLike('student_number', $like))
                ->orWhereHas('industryPartner', fn (Builder $p) => $p->whereLike('company_name', $like))));

        match ($filters['filter'] ?? 'all') {
            'active' => $query->where('status', DeploymentStatus::Active),
            'overdue' => $this->whereOverdue($query),
            'completed' => $query->where('status', DeploymentStatus::Completed),
            'withdrawn' => $query->where('status', DeploymentStatus::Withdrawn),
            default => null,
        };

        $page = $query
            ->orderByRaw("case deployments.status when 'active' then 0 when 'completed' then 1 else 2 end")
            ->orderBy(DB::table('students')
                ->join('users', 'users.id', '=', 'students.user_id')
                ->whereColumn('students.id', 'deployments.student_id')
                ->select('users.name'))
            ->orderBy('deployments.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $page->getCollection()->each(fn (Deployment $d) => $d->setAttribute('progress', $this->progress($d)));

        return ['deployments' => $page, 'scope_total' => $this->inScope($viewer)->count()];
    }

    /**
     * @return array{deployment: Deployment, progress: array<string, mixed>}
     */
    public function detail(Deployment $deployment): array
    {
        $deployment->load([
            'student.user.program',
            'industryPartner.user',
            'weeklyReports' => fn ($q) => $q->orderBy('week_number')->with('signedBy'),
            'completedBy',
        ]);

        return ['deployment' => $deployment, 'progress' => $this->progress($deployment)];
    }

    /**
     * @return array{active: int, overdue: int, completed: int, withdrawn: int, reports_awaiting_signature: int, average_percent: float|null}
     */
    public function summary(User $viewer): array
    {
        $active = $this->withProgress($this->inScope($viewer)->where('status', DeploymentStatus::Active))
            ->with('student.user.program')
            ->get();
        $percents = $active->map(fn (Deployment $d) => $this->progress($d)['percent']);

        return [
            'active' => $active->count(),
            'overdue' => $this->whereOverdue($this->inScope($viewer))->count(),
            'completed' => $this->inScope($viewer)->where('status', DeploymentStatus::Completed)->count(),
            'withdrawn' => $this->inScope($viewer)->where('status', DeploymentStatus::Withdrawn)->count(),
            'reports_awaiting_signature' => WeeklyReport::query()
                ->where('status', WeeklyReportStatus::Submitted)
                ->whereIn('deployment_id', $this->inScope($viewer)->select('deployments.id'))
                ->count(),
            'average_percent' => $percents->isEmpty() ? null : round($percents->avg(), 1),
        ];
    }

    /**
     * Complete an ACTIVE deployment once signed hours meet the program's requirement and
     * (gias.evaluation.require_final_for_completion) its FINAL evaluation is reviewed.
     * Otherwise 422, unless an ADMIN forces it with a reason.
     * Returns the completed deployment's detail.
     *
     * @return array{deployment: Deployment, progress: array<string, mixed>}
     */
    public function complete(User $actor, Deployment $deployment, bool $force, ?string $note): array
    {
        Gate::forUser($actor)->authorize('complete', $deployment);

        $completed = DB::transaction(function () use ($actor, $deployment, $force, $note) {
            $locked = Deployment::query()->lockForUpdate()->findOrFail($deployment->id);

            if ($locked->status !== DeploymentStatus::Active) {
                throw ValidationException::withMessages(['deployment' => 'Only an active deployment can be completed.']);
            }

            $progress = $this->progress($locked);
            $short = $progress['signed_hours'] < $progress['required_hours'];
            $finalMissing = config('gias.evaluation.require_final_for_completion')
                && ! $locked->final()->where('status', EvaluationStatus::Reviewed)->exists();

            if (($short || $finalMissing) && ! $force) {
                throw ValidationException::withMessages(['deployment' => $short
                    ? sprintf(
                        '%s more countersigned hours are needed (%s of %d h signed).',
                        $this->hours($progress['missing_hours']),
                        $this->hours($progress['signed_hours']),
                        $progress['required_hours'],
                    )
                    : 'The final evaluation hasn’t been reviewed yet.']);
            }
            if (($short || $finalMissing) && $actor->role !== UserRole::Admin) {
                throw new AccessDeniedHttpException(
                    'Only an Admin can complete a deployment below the required hours or without a reviewed final evaluation.',
                );
            }

            $locked->update([
                'status' => DeploymentStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'completion_note' => $note,
                'end_date' => $locked->end_date ?? WeeklyReportService::today(),
            ]);
            DeploymentCompleted::dispatch($locked); // after commit

            return $locked;
        });

        return $this->detail($completed);
    }

    // Student

    /**
     * The student's active deployment, or their latest one; 404 if never deployed.
     *
     * @return array{deployment: Deployment, progress: array<string, mixed>, coordinator: User|null, weeks: list<array<string, mixed>>}
     */
    public function forStudent(User $studentUser): array
    {
        $deployment = $studentUser->student?->deployments()
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (! $deployment) {
            throw new NotFoundHttpException('You haven’t been placed yet. Your OJT Coordinator will assign your Industry Partner.');
        }

        $deployment->load([
            'industryPartner',
            'student.user.program',
            'weeklyReports' => fn ($q) => $q->orderBy('week_number'),
        ]);

        $coordinator = User::query()
            ->where('role', UserRole::Coordinator)
            ->where('status', UserStatus::Active)
            ->where('program_id', $studentUser->program_id)
            ->first();

        return [
            'deployment' => $deployment,
            'progress' => $this->progress($deployment),
            'coordinator' => $coordinator,
            'weeks' => $this->reports->availableWeeks($deployment),
        ];
    }

    // Industry partner

    /**
     * Active and completed deployments at the partner's site, with progress.
     *
     * @return Collection<int, Deployment>
     */
    public function partnerInterns(User $partnerUser): Collection
    {
        $profileId = $partnerUser->industryPartner()->value('id');

        return $this->withProgress(Deployment::query())
            ->where('industry_partner_id', $profileId)
            ->whereIn('status', [DeploymentStatus::Active, DeploymentStatus::Completed])
            ->with('student.user.program')
            ->orderByRaw("case status when 'active' then 0 else 1 end")
            ->orderBy('start_date')
            ->get()
            ->each(fn (Deployment $d) => $d->setAttribute('progress', $this->progress($d)));
    }

    // Scope helpers

    /**
     * Deployments of students the viewer may monitor (admin: all; coordinator: own program).
     * Shared with the evaluation services.
     *
     * @return Builder<Deployment>
     */
    public function inScope(User $viewer): Builder
    {
        return Deployment::query()->when($viewer->role !== UserRole::Admin, fn (Builder $q) => $q->whereHas(
            'student.user',
            fn (Builder $u) => $viewer->program_id !== null
                ? $u->where('program_id', $viewer->program_id)
                : $u->whereRaw('false'),
        ));
    }

    /**
     * @param  Builder<Deployment>  $query
     * @return Builder<Deployment>
     */
    public function whereOverdue(Builder $query): Builder
    {
        return $query
            ->where('deployments.status', DeploymentStatus::Active)
            ->whereRaw(
                'coalesce((select max(w.period_end) from weekly_reports w where w.deployment_id = deployments.id '
                .'and w.status in (?, ?)), deployments.start_date) < ?',
                [WeeklyReportStatus::Submitted->value, WeeklyReportStatus::Signed->value, self::overdueCutoff()],
            );
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
