<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    /**
     * Approve / return a student's registration.
     *
     * Admin: any student. Coordinator: only students of their own program, and only while
     * the coordinator is active. So a program with no active coordinator can only be
     * handled by an Admin.
     */
    public function reviewStudent(User $actor, User $student): bool
    {
        return $this->manageStudent($actor, $student);
    }

    /**
     * See recommendations for, place, withdraw and rate a student. Same scope as reviewing:
     * admin = every student; an active coordinator = students of their own program.
     */
    public function manageStudent(User $actor, User $student): bool
    {
        if ($student->role !== UserRole::Student) {
            return false;
        }

        return match ($actor->role) {
            UserRole::Admin => true,
            UserRole::Coordinator => $actor->status === UserStatus::Active
                && $actor->program_id !== null
                && $actor->program_id === $student->program_id,
            default => false,
        };
    }
}
