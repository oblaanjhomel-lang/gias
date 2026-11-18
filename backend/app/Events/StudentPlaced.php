<?php

namespace App\Events;

use App\Models\Deployment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A coordinator / admin placed a student (PlacementService).
 * Dispatched after the database transaction commits (a rolled-back batch sends nothing).
 */
class StudentPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Deployment $deployment) {}
}
