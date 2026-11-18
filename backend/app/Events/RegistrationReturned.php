<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A registration was returned (pending → rejected) with a reason.
 * Dispatched after the database transaction commits.
 */
class RegistrationReturned implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly User $user, public readonly string $reason) {}
}
