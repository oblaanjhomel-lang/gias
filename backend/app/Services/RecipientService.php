<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who should hear about something. Shared by RegistrationService (the confirmation message)
 * and the notification listeners.
 */
class RecipientService
{
    public const PROGRAM_COORDINATOR = "your program's OJT Coordinator";

    public const ADMIN = 'the GIAS Admin';

    /**
     * @return Collection<int, User>
     */
    public function activeAdmins(): Collection
    {
        return User::query()->where('role', UserRole::Admin)->where('status', UserStatus::Active)->get();
    }

    public function programCoordinator(?int $programId): ?User
    {
        if ($programId === null) {
            return null;
        }

        return User::query()
            ->where('role', UserRole::Coordinator)
            ->where('status', UserStatus::Active)
            ->where('program_id', $programId)
            ->first();
    }

    /**
     * The program's active coordinator, or every active admin when the program has none.
     *
     * @return Collection<int, User>
     */
    public function programReviewers(?int $programId): Collection
    {
        $coordinator = $this->programCoordinator($programId);

        return $coordinator ? new Collection([$coordinator]) : $this->activeAdmins();
    }

    /**
     * Reviewers of a new registration and how the registrant should be told about them.
     * Students: their program's coordinator (or the admins). Partners: the admins.
     *
     * @return array{users: Collection<int, User>, label: string}
     */
    public function registrationReviewers(User $registrant): array
    {
        $coordinator = $registrant->role === UserRole::Student ? $this->programCoordinator($registrant->program_id) : null;

        return $coordinator
            ? ['users' => new Collection([$coordinator]), 'label' => self::PROGRAM_COORDINATOR]
            : ['users' => $this->activeAdmins(), 'label' => self::ADMIN];
    }

    /** "/admin/{section}" for admins, "/coordinator/{section}" for coordinators. */
    public function reviewPath(User $reviewer, string $section): string
    {
        return ($reviewer->role === UserRole::Admin ? '/admin/' : '/coordinator/').$section;
    }
}
