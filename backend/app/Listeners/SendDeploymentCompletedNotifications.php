<?php

namespace App\Listeners;

use App\Events\DeploymentCompleted;
use App\Notifications\DeploymentCompletedNotice;

/**
 * The student and the hosting partner.
 */
class SendDeploymentCompletedNotifications
{
    use SendsNotifications;

    public function handle(DeploymentCompleted $event): void
    {
        $deployment = $event->deployment->loadMissing(['student.user', 'industryPartner.user']);

        $this->send([$deployment->student->user], DeploymentCompletedNotice::forStudent($deployment));
        $this->send([$deployment->industryPartner->user], DeploymentCompletedNotice::forPartner($deployment));
    }
}
