<?php

namespace App\Policies;

use App\Models\Deployment;
use App\Models\User;

/**
 * Monitoring scope: admin = every deployment; an active coordinator = deployments of
 * students in their own program (same rule as UserPolicy::manageStudent).
 */
class DeploymentPolicy
{
    public function view(User $user, Deployment $deployment): bool
    {
        $studentUser = $deployment->student?->user;

        return $studentUser !== null && (new UserPolicy)->manageStudent($user, $studentUser);
    }

    public function complete(User $user, Deployment $deployment): bool
    {
        return $this->view($user, $deployment);
    }
}
