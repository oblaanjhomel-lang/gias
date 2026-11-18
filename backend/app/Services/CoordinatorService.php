<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoordinatorService
{
    public function __construct(private readonly AccountService $accounts) {}

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return array{coordinators: LengthAwarePaginator<int, User>, programs_without_active_coordinator: Collection<int, Program>}
     */
    public function list(array $filters): array
    {
        $search = trim($filters['search'] ?? '');
        $like = $this->accounts->containsPattern($search);

        $coordinators = User::query()
            ->where('role', UserRole::Coordinator)
            ->with('program')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereLike('name', $like)
                ->orWhereLike('email', $like)
                ->orWhereHas('program', fn (Builder $p) => $p->whereLike('name', $like)->orWhereLike('code', $like))))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return [
            'coordinators' => $coordinators,
            'programs_without_active_coordinator' => $this->programsWithoutActiveCoordinator(),
        ];
    }

    /**
     * @param  array{name: string, email: string, program_id: int}  $data
     */
    public function create(array $data): User
    {
        $coordinator = DB::transaction(function () use ($data) {
            $this->ensureProgramIsFree((int) $data['program_id']);

            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $this->accounts->randomPassword(),
                'role' => UserRole::Coordinator,
                'status' => UserStatus::Active,
                'program_id' => $data['program_id'],
            ]);
        });

        $this->accounts->sendSetPasswordLink($coordinator);

        return $coordinator->load('program');
    }

    /**
     * @param  array{name: string, email: string, program_id: int, status?: string}  $data
     */
    public function update(User $actor, User $coordinator, array $data): User
    {
        $this->accounts->ensureManageable($actor, $coordinator);

        DB::transaction(function () use ($coordinator, $data) {
            $status = isset($data['status']) ? UserStatus::from($data['status']) : $coordinator->status;

            if ($status === UserStatus::Active) {
                $this->ensureProgramIsFree((int) $data['program_id'], $coordinator->id);
            }

            $coordinator->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'program_id' => $data['program_id'],
            ])->save();

            if ($status !== $coordinator->status) {
                $this->accounts->applyStatus($coordinator, $status);
            }
        });

        return $coordinator->load('program');
    }

    public function setStatus(User $actor, User $coordinator, UserStatus $status): User
    {
        $this->accounts->ensureManageable($actor, $coordinator);

        DB::transaction(function () use ($coordinator, $status) {
            if ($status === UserStatus::Active && $coordinator->program_id !== null) {
                $this->ensureProgramIsFree($coordinator->program_id, $coordinator->id);
            }

            $this->accounts->applyStatus($coordinator, $status);
        });

        return $coordinator->load('program');
    }

    public function delete(User $actor, User $coordinator): void
    {
        $this->accounts->delete($actor, $coordinator);
    }

    /**
     * Only one ACTIVE coordinator per program. Locks the program row so two concurrent
     * requests can't both pass the check. Must run inside a transaction.
     */
    private function ensureProgramIsFree(int $programId, ?int $exceptUserId = null): void
    {
        $program = Program::query()->lockForUpdate()->findOrFail($programId);

        $taken = User::query()
            ->where('role', UserRole::Coordinator)
            ->where('status', UserStatus::Active)
            ->where('program_id', $programId)
            ->when($exceptUserId, fn (Builder $q, int $id) => $q->whereKeyNot($id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'program_id' => "{$program->name} already has an active coordinator. Deactivate that account first.",
            ]);
        }
    }

    /**
     * @return Collection<int, Program>
     */
    private function programsWithoutActiveCoordinator(): Collection
    {
        return Program::query()
            ->whereDoesntHave('users', fn (Builder $q) => $q
                ->where('role', UserRole::Coordinator)
                ->where('status', UserStatus::Active))
            ->orderBy('code')
            ->get();
    }
}
