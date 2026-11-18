<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\PlacementWithdrawn;
use App\Events\StudentPlaced;
use App\Models\Deployment;
use App\Models\IndustryPartner;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY place a deployment is created: a Coordinator (own program) or Admin action.
 * Each placement records the partner's rank under each algorithm and a snapshot of the
 * recommendation at that moment, for evaluation.
 */
class PlacementService
{
    public function __construct(private readonly RecommendationService $recommendations) {}

    /**
     * Ids are USER ids of the student and the partner.
     */
    public function placeByIds(User $actor, int $studentUserId, int $partnerUserId, ?string $algorithm, string $startDate): Deployment
    {
        return $this->place(
            $actor,
            User::query()->where('role', UserRole::Student)->findOrFail($studentUserId),
            User::query()->where('role', UserRole::Partner)->findOrFail($partnerUserId),
            $algorithm,
            $startDate,
        );
    }

    public function place(User $actor, User $studentUser, User $partnerUser, ?string $algorithm, string $startDate): Deployment
    {
        $prepared = $this->prepare($actor, $studentUser, $partnerUser, $algorithm, $startDate);

        return DB::transaction(fn () => $this->placeLocked($actor, $prepared));
    }

    /**
     * All-or-nothing: if any item fails, every placement in the batch is rolled back and the
     * error names the failing item (items.{index}).
     *
     * @param  list<array{student_id: int, partner_id: int, algorithm?: string|null, start_date: string}>  $items
     * @return list<Deployment>
     */
    public function placeBatch(User $actor, array $items): array
    {
        $prepared = [];
        foreach ($items as $i => $item) {
            $prepared[$i] = $this->prepare(
                $actor,
                User::query()->where('role', UserRole::Student)->findOrFail($item['student_id']),
                User::query()->where('role', UserRole::Partner)->findOrFail($item['partner_id']),
                $item['algorithm'] ?? null,
                $item['start_date'],
            );
        }

        return DB::transaction(function () use ($actor, $prepared) {
            $created = [];
            foreach ($prepared as $i => $item) {
                try {
                    $created[] = $this->placeLocked($actor, $item);
                } catch (ValidationException $e) {
                    $reason = collect($e->errors())->flatten()->first();
                    $company = $item['partnerUser']->industryPartner?->company_name ?? 'partner';

                    throw ValidationException::withMessages([
                        "items.{$i}" => 'Item '.($i + 1)." ({$item['studentUser']->name} → {$company}): {$reason} Nothing was placed.",
                    ]);
                }
            }

            return $created;
        });
    }

    /**
     * active → withdrawn (end_date = today). The slot opens again.
     */
    public function withdraw(User $actor, Deployment $deployment): Deployment
    {
        $deployment->loadMissing('student.user');
        Gate::forUser($actor)->authorize('manageStudent', $deployment->student->user);

        if ($deployment->status !== DeploymentStatus::Active) {
            throw ValidationException::withMessages(['deployment' => 'Only an active placement can be withdrawn.']);
        }

        $deployment->update(['status' => DeploymentStatus::Withdrawn, 'end_date' => now()->toDateString()]);
        PlacementWithdrawn::dispatch($deployment);

        return $deployment->load(['student.user', 'industryPartner']);
    }

    /**
     * Outside the transaction: scope check and the (read-only, heavier) recommendation snapshot.
     *
     * @return array<string, mixed>
     */
    private function prepare(User $actor, User $studentUser, User $partnerUser, ?string $algorithm, string $startDate): array
    {
        Gate::forUser($actor)->authorize('manageStudent', $studentUser);

        $result = $this->recommendations->recommendFor($studentUser, $actor);
        $snapshot = $this->recommendations->snapshot($result);

        $ranks = [];
        foreach (RecommendationService::ALGORITHMS as $a) {
            $entry = collect($result['rankings'][$a])->firstWhere('partner_id', $partnerUser->id);
            $ranks[$a] = $entry['rank'] ?? null;
        }

        return [
            'studentUser' => $studentUser,
            'partnerUser' => $partnerUser,
            'algorithm' => $algorithm ?? 'manual',
            'startDate' => $startDate,
            'ranks' => $ranks,
            'snapshot' => $snapshot + ['algorithm_used' => $algorithm ?? 'manual'],
        ];
    }

    /**
     * Inside the caller's transaction. The partner row is locked (SELECT … FOR UPDATE) so two
     * coordinators can't both take its last slot; the partial unique index stops a student
     * from getting two active deployments.
     *
     * @param  array<string, mixed>  $item
     */
    private function placeLocked(User $actor, array $item): Deployment
    {
        /** @var User $studentUser */
        $studentUser = $item['studentUser'];
        /** @var User $partnerUser */
        $partnerUser = $item['partnerUser'];

        $partner = IndustryPartner::query()->where('user_id', $partnerUser->id)->lockForUpdate()->first();
        $student = $studentUser->student()->first();

        if (! $partner || ! $student) {
            $this->fail('student_id', 'Student or partner profile not found.');
        }
        if ($studentUser->fresh()->status !== UserStatus::Active) {
            $this->fail('student_id', "{$studentUser->name} is not an active (approved) student.");
        }
        if ($partnerUser->fresh()->status !== UserStatus::Active) {
            $this->fail('partner_id', "{$partner->company_name} is not an active partner.");
        }
        if ($student->activeDeployment()->exists()) {
            $this->fail('student_id', "{$studentUser->name} is already placed. Withdraw that placement first.");
        }

        $filled = Deployment::query()
            ->where('industry_partner_id', $partner->id)
            ->where('status', DeploymentStatus::Active)
            ->count();
        if ($filled >= $partner->intern_slots) {
            $this->fail('partner_id', "{$partner->company_name} has no open slots.");
        }

        try {
            $deployment = Deployment::create([
                'student_id' => $student->id,
                'industry_partner_id' => $partner->id,
                'status' => DeploymentStatus::Active,
                'start_date' => $item['startDate'],
                'assigned_by' => $actor->id,
                'algorithm_used' => $item['algorithm'],
                'rank_cos' => $item['ranks']['cos'],
                'rank_wsm' => $item['ranks']['wsm'],
                'rank_fuzzy' => $item['ranks']['fuzzy'],
                'recommendation_snapshot' => $item['snapshot'],
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->fail('student_id', "{$studentUser->name} is already placed. Withdraw that placement first.");
        }

        StudentPlaced::dispatch($deployment); // after commit; a rolled-back batch sends nothing

        return $deployment->load(['student.user', 'industryPartner']);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
