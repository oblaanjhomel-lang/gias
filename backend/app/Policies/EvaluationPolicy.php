<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\User;

/**
 * Ownership / scope only. The status flow and "is it open / active" rules are enforced in
 * EvaluationService (422); failing these checks answers 403.
 */
class EvaluationPolicy
{
    /** The Industry Partner hosting the deployment. */
    public function partnerEvaluate(User $user, Deployment $deployment): bool
    {
        return $user->role === UserRole::Partner
            && $deployment->industryPartner?->user_id === $user->id;
    }

    /** Coordinator of the student's program (active) or an admin. */
    public function review(User $user, Evaluation $evaluation): bool
    {
        $studentUser = $evaluation->deployment?->student?->user;

        return $studentUser !== null && (new UserPolicy)->manageStudent($user, $studentUser);
    }
}
