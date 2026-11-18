<?php

namespace App\Events;

use App\Models\Deployment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An active placement was withdrawn (PlacementService).
 * Dispatched after the database transaction commits.
 */
class PlacementWithdrawn implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Deployment $deployment) {}
}
