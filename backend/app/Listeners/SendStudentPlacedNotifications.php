<?php

namespace App\Listeners;

use App\Events\StudentPlaced;
use App\Notifications\StudentPlaced as StudentPlacedNotification;

/**
 * The student and the hosting partner.
 */
class SendStudentPlacedNotifications
{
    use SendsNotifications;

    public function handle(StudentPlaced $event): void
    {
        $deployment = $event->deployment->loadMissing(['student.user.program', 'industryPartner.user']);

        $this->send([$deployment->student->user], StudentPlacedNotification::forStudent($deployment));
        $this->send([$deployment->industryPartner->user], StudentPlacedNotification::forPartner($deployment));
    }
}
