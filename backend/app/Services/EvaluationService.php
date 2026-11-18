<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Events\EvaluationReturned;
use App\Events\EvaluationReviewed;
use App\Events\EvaluationSubmitted;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Intern evaluations (config gias.evaluation). Ownership / scope: EvaluationPolicy on the routes.
 *
 *  Partner:      draft (partial ratings OK) → submitted (all six, sets partner_mean + snapshot)
 *  Coordinator:  submitted → reviewed (needs coordinator_rating; final = 0.70·mean + 0.30·rating)
 *                submitted → returned (note) → partner edits and resubmits
 *                reviewed → submitted (reopen; not once the deployment is completed)
 *  Student:      sees REVIEWED evaluations only.
 */
class EvaluationService
{
    public function __construct(private readonly DeploymentMonitoringService $monitoring) {}

    /** @return array<string, string> key => label, in order */
    public static function criteria(): array
    {
        return config('gias.evaluation.criteria');
    }

    public static function descriptor(float|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        foreach (config('gias.evaluation.descriptor_bands') as $band) {
            if ((float) $value >= $band['min']) {
                return $band['label'];
            }
        }

        return null;
    }

    public static function finalRating(float $partnerMean, int $coordinatorRating): float
    {
        $w = config('gias.evaluation.weights');

        return round($w['partner'] * $partnerMean + $w['coordinator'] * $coordinatorRating, 2);
    }

    /**
     * A type opens once SIGNED hours reach opens_at_percent of the program's required hours.
     *
     * @return array{open: bool, threshold_hours: float, signed_hours: float, required_hours: int}
     */
    public function openState(Deployment $deployment, EvaluationType $type): array
    {
        $progress = $this->monitoring->progress($deployment);
        $threshold = round(config("gias.evaluation.opens_at_percent.{$type->value}") * $progress['required_hours'] / 100, 2);

        return [
            'open' => $progress['signed_hours'] >= $threshold,
            'threshold_hours' => $threshold,
            'signed_hours' => $progress['signed_hours'],
            'required_hours' => $progress['required_hours'],
        ];
    }

    // Industry partner

    /**
     * Active deployments at the partner's site with both evaluations' state.
     *
     * @return array{rows: list<array<string, mixed>>, finals_submitted: int, total: int}
     */
    public function partnerOverview(User $partnerUser): array
    {
        $profileId = $partnerUser->industryPartner()->value('id');

        $deployments = $this->monitoring->withProgress(Deployment::query())
            ->where('industry_partner_id', $profileId)
            ->where('status', DeploymentStatus::Active)
            ->with(['student.user.program', 'evaluations'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $rows = $deployments->map(function (Deployment $d) {
            $progress = $this->monitoring->progress($d);
            $row = [
                'deployment' => $d,
                'signed_hours' => $progress['signed_hours'],
                'required_hours' => $progress['required_hours'],
            ];
            foreach (EvaluationType::cases() as $type) {
                $state = $this->openState($d, $type);
                $row[$type->value] = [
                    'status' => $d->evaluations->firstWhere('type', $type)?->status->value,
                    'open' => $state['open'],
                    'threshold_hours' => $state['threshold_hours'],
                ];
            }

            return $row;
        })->all();

        return [
            'rows' => $rows,
            'finals_submitted' => collect($rows)
                ->filter(fn (array $r) => in_array($r['final']['status'], ['submitted', 'reviewed'], true))
                ->count(),
            'total' => count($rows),
        ];
    }

    /**
     * @return array{deployment: Deployment, type: EvaluationType, open: array<string, mixed>, evaluation: Evaluation|null}
     */
    public function forPartner(Deployment $deployment, EvaluationType $type): array
    {
        $deployment->loadMissing('student.user.program');

        return [
            'deployment' => $deployment,
            'type' => $type,
            'open' => $this->openState($deployment, $type),
            'evaluation' => $deployment->evaluations()->where('type', $type)->first(),
        ];
    }

    /**
     * Create or update the partner's draft. Ratings may be partial.
     *
     * @param  array<string, int>  $ratings
     * @return array{deployment: Deployment, type: EvaluationType, open: array<string, mixed>, evaluation: Evaluation|null}
     */
    public function saveDraft(User $partnerUser, Deployment $deployment, EvaluationType $type, array $ratings, ?string $comment): array
    {
        $this->ensureEvaluable($deployment, $type);

        DB::transaction(function () use ($deployment, $type, $ratings, $comment) {
            $evaluation = $this->lockFor($deployment, $type);

            if ($evaluation && ! $evaluation->status->isEditableByPartner()) {
                $this->fail('status', "This evaluation is {$evaluation->status->value} and can’t be edited.");
            }

            $evaluation ??= new Evaluation([
                'deployment_id' => $deployment->id,
                'type' => $type,
                'status' => EvaluationStatus::Draft,
            ]);
            $evaluation->fill([
                'ratings' => $this->cleanRatings($ratings),
                'comment' => $comment,
            ])->save();
        });

        return $this->forPartner($deployment, $type);
    }

    /**
     * @return array{deployment: Deployment, type: EvaluationType, open: array<string, mixed>, evaluation: Evaluation|null}
     */
    public function submit(User $partnerUser, Deployment $deployment, EvaluationType $type): array
    {
        $this->ensureEvaluable($deployment, $type);

        DB::transaction(function () use ($partnerUser, $deployment, $type) {
            $evaluation = $this->lockFor($deployment, $type);
            $criteria = self::criteria();

            if (! $evaluation) {
                $this->fail('ratings', sprintf('Rate all %d criteria before submitting (0 of %d done).', count($criteria), count($criteria)));
            }
            if (! $evaluation->status->isEditableByPartner()) {
                $this->fail('status', "This evaluation is already {$evaluation->status->value}.");
            }

            $ratings = $this->cleanRatings($evaluation->ratings ?? []);
            if (count($ratings) < count($criteria)) {
                $this->fail('ratings', sprintf(
                    'Rate all %d criteria before submitting (%d of %d done).',
                    count($criteria), count($ratings), count($criteria),
                ));
            }

            $evaluation->update([
                'ratings' => $ratings,
                'criteria_snapshot' => collect($criteria)
                    ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                    ->values()
                    ->all(),
                'partner_mean' => round(array_sum($ratings) / count($ratings), 2),
                'status' => EvaluationStatus::Submitted,
                'submitted_by' => $partnerUser->id,
                'submitted_at' => now(),
            ]);
            EvaluationSubmitted::dispatch($evaluation); // after commit
        });

        return $this->forPartner($deployment, $type);
    }

    // Coordinator / admin

    /**
     * Deployments in scope (active or completed) with their evaluation of `type`, plus counts.
     *
     * @param  array{type?: string|null, status?: string|null, search?: string|null, program_id?: int|null}  $filters
     * @return array{deployments: LengthAwarePaginator<int, Deployment>, counts: array<string, int>, type: EvaluationType}
     */
    public function list(User $viewer, array $filters): array
    {
        $type = EvaluationType::from($filters['type'] ?? EvaluationType::Final->value);
        $relation = $type === EvaluationType::Midterm ? 'midterm' : 'final';
        $search = trim($filters['search'] ?? '');
        $like = '%'.addcslashes($search, '%_\\').'%';
        $isAdmin = $viewer->role === UserRole::Admin;

        $base = fn () => $this->monitoring->inScope($viewer)
            ->whereIn('deployments.status', [DeploymentStatus::Active, DeploymentStatus::Completed])
            ->when($isAdmin && ($filters['program_id'] ?? null), fn (Builder $q) => $q->whereHas(
                'student.user', fn (Builder $u) => $u->where('program_id', (int) $filters['program_id']),
            ));

        $status = $filters['status'] ?? null;
        $page = $this->monitoring->withProgress($base())
            ->with(['student.user.program', 'industryPartner', $relation])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereHas('student.user', fn (Builder $u) => $u->whereLike('name', $like))
                ->orWhereHas('student', fn (Builder $s) => $s->whereLike('student_number', $like))
                ->orWhereHas('industryPartner', fn (Builder $p) => $p->whereLike('company_name', $like))))
            ->when($status === 'not_started', fn (Builder $q) => $q->whereDoesntHave($relation))
            ->when($status && $status !== 'not_started', fn (Builder $q) => $q->whereHas(
                $relation, fn (Builder $e) => $e->where('status', $status),
            ))
            ->orderByRaw(
                'case (select e.status from evaluations e where e.deployment_id = deployments.id and e.type = ?) '
                ."when 'submitted' then 0 when 'returned' then 1 when 'draft' then 2 when 'reviewed' then 4 else 3 end",
                [$type->value],
            )
            ->orderBy(DB::table('students')->join('users', 'users.id', '=', 'students.user_id')
                ->whereColumn('students.id', 'deployments.student_id')->select('users.name'))
            ->paginate(15)
            ->withQueryString();

        $page->getCollection()->each(fn (Deployment $d) => $d->setAttribute('progress', $this->monitoring->progress($d)));

        $counts = DB::table('deployments as d')
            ->leftJoin('evaluations as e', fn ($join) => $join->on('e.deployment_id', '=', 'd.id')->where('e.type', '=', $type->value))
            ->whereIn('d.id', $base()->select('deployments.id'))
            ->selectRaw("coalesce(e.status, 'not_started') as s, count(*) as n")
            ->groupBy('s')
            ->pluck('n', 's')
            ->map(fn ($n) => (int) $n);

        return [
            'deployments' => $page,
            'type' => $type,
            'counts' => [
                'to_review' => $counts['submitted'] ?? 0,
                'reviewed' => $counts['reviewed'] ?? 0,
                'not_submitted' => ($counts['not_started'] ?? 0) + ($counts['draft'] ?? 0) + ($counts['returned'] ?? 0),
            ],
        ];
    }

    public function detail(Evaluation $evaluation): Evaluation
    {
        $evaluation->load(['deployment.student.user.program', 'deployment.industryPartner', 'submittedBy', 'reviewedBy']);
        $evaluation->deployment->setAttribute('progress', $this->monitoring->progress($evaluation->deployment));

        return $evaluation;
    }

    public function setCoordinatorRating(User $actor, Evaluation $evaluation, int $rating, ?string $note): Evaluation
    {
        DB::transaction(function () use ($evaluation, $rating, $note) {
            $locked = $this->lock($evaluation);
            $this->ensureStatus($locked, EvaluationStatus::Submitted, 'rated');
            $locked->update(['coordinator_rating' => $rating, 'coordinator_note' => $note]);
        });

        return $this->detail($evaluation->refresh());
    }

    public function review(User $actor, Evaluation $evaluation): Evaluation
    {
        DB::transaction(function () use ($actor, $evaluation) {
            $locked = $this->lock($evaluation);
            $this->ensureStatus($locked, EvaluationStatus::Submitted, 'reviewed');

            if ($locked->coordinator_rating === null) {
                $this->fail('coordinator_rating', 'Add your rating before marking it as reviewed.');
            }

            $locked->update([
                'status' => EvaluationStatus::Reviewed,
                'final_rating' => self::finalRating((float) $locked->partner_mean, $locked->coordinator_rating),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
            EvaluationReviewed::dispatch($locked);
        });

        return $this->detail($evaluation->refresh());
    }

    public function reopen(User $actor, Evaluation $evaluation): Evaluation
    {
        DB::transaction(function () use ($evaluation) {
            $locked = $this->lock($evaluation);
            $this->ensureStatus($locked, EvaluationStatus::Reviewed, 'reopened');

            if ($locked->deployment->status === DeploymentStatus::Completed) {
                $this->fail('status', 'The deployment is already completed, so its evaluation can’t be reopened.');
            }

            $locked->update(['status' => EvaluationStatus::Submitted, 'final_rating' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
        });

        return $this->detail($evaluation->refresh());
    }

    public function returnToPartner(User $actor, Evaluation $evaluation, string $note): Evaluation
    {
        DB::transaction(function () use ($evaluation, $note) {
            $locked = $this->lock($evaluation);
            $this->ensureStatus($locked, EvaluationStatus::Submitted, 'returned');

            $locked->update(['status' => EvaluationStatus::Returned, 'return_note' => $note]);
            EvaluationReturned::dispatch($locked);
        });

        return $this->detail($evaluation->refresh());
    }

    // Student

    /**
     * REVIEWED evaluations of the student's own deployments (latest deployment first).
     *
     * @return Collection<int, Evaluation>
     */
    public function forStudent(User $studentUser): Collection
    {
        return Evaluation::query()
            ->select('evaluations.*')
            ->join('deployments', 'deployments.id', '=', 'evaluations.deployment_id')
            ->where('deployments.student_id', $studentUser->student?->id ?? 0)
            ->where('evaluations.status', EvaluationStatus::Reviewed)
            ->orderByDesc('deployments.start_date')
            ->orderBy('evaluations.type')
            ->get();
    }

    // Helpers

    private function ensureEvaluable(Deployment $deployment, EvaluationType $type): void
    {
        if ($deployment->status !== DeploymentStatus::Active) {
            $this->fail('deployment', 'Only interns with an active deployment can be evaluated.');
        }

        $state = $this->openState($deployment, $type);
        if (! $state['open']) {
            $this->fail('type', sprintf(
                'The %s evaluation opens at %s signed hours (now %s).',
                $type->value,
                $this->hours($state['threshold_hours']),
                $this->hours($state['signed_hours']),
            ));
        }
    }

    /**
     * Keep only known criteria with integer values 1–5 (the Form Request validates too).
     *
     * @param  array<string, mixed>  $ratings
     * @return array<string, int>
     */
    private function cleanRatings(array $ratings): array
    {
        $clean = [];
        foreach (array_keys(self::criteria()) as $key) {
            if (isset($ratings[$key]) && (int) $ratings[$key] >= 1 && (int) $ratings[$key] <= 5) {
                $clean[$key] = (int) $ratings[$key];
            }
        }

        return $clean;
    }

    private function lockFor(Deployment $deployment, EvaluationType $type): ?Evaluation
    {
        return Evaluation::query()
            ->where('deployment_id', $deployment->id)
            ->where('type', $type)
            ->lockForUpdate()
            ->first();
    }

    private function lock(Evaluation $evaluation): Evaluation
    {
        return Evaluation::query()->with('deployment')->lockForUpdate()->findOrFail($evaluation->id);
    }

    private function ensureStatus(Evaluation $evaluation, EvaluationStatus $expected, string $action): void
    {
        if ($evaluation->status !== $expected) {
            $this->fail('status', "Only {$expected->value} evaluations can be {$action}; this one is {$evaluation->status->value}.");
        }
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
