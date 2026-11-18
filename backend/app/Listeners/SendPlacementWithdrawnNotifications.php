<?php

namespace App\Listeners;

use App\Events\PlacementWithdrawn;
use App\Notifications\PlacementWithdrawn as PlacementWithdrawnNotification;

/**
 * The student and the hosting partner.
 */
class SendPlacementWithdrawnNotifications
{
    use SendsNotifications;

    public function handle(PlacementWithdrawn $event): void
    {
        $deployment = $event->deployment->loadMissing(['student.user', 'industryPartner.user']);

        $this->send([$deployment->student->user], PlacementWithdrawnNotification::forStudent($deployment));
        $this->send([$deployment->industryPartner->user], PlacementWithdrawnNotification::forPartner($deployment));
    }
}
