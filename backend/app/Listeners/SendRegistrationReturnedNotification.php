<?php

namespace App\Listeners;

use App\Events\RegistrationReturned;
use App\Notifications\RegistrationReturned as RegistrationReturnedNotification;

class SendRegistrationReturnedNotification
{
    use SendsNotifications;

    public function handle(RegistrationReturned $event): void
    {
        $this->send([$event->user], new RegistrationReturnedNotification($event->reason));
    }
}
