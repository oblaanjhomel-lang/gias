<?php

namespace App\Events;

use App\Models\Deployment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after the database transaction commits. No listeners yet (Module 8: notifications).
 */
class DeploymentCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Deployment $deployment) {}
}
