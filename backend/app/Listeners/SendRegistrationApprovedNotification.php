<?php

namespace App\Listeners;

use App\Events\RegistrationApproved;
use App\Notifications\RegistrationApproved as RegistrationApprovedNotification;

class SendRegistrationApprovedNotification
{
    use SendsNotifications;

    public function handle(RegistrationApproved $event): void
    {
        $this->send([$event->user], new RegistrationApprovedNotification);
    }
}
