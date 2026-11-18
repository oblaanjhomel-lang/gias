<?php

namespace App\Listeners;

use App\Events\EvaluationReturned;
use App\Notifications\EvaluationReturnedToPartner;

/**
 * The hosting partner: the evaluation came back with the coordinator's note.
 */
class SendEvaluationReturnedNotification
{
    use SendsNotifications;

    public function handle(EvaluationReturned $event): void
    {
        $evaluation = $event->evaluation->loadMissing(['deployment.student.user', 'deployment.industryPartner.user']);

        $this->send([$evaluation->deployment->industryPartner->user], new EvaluationReturnedToPartner($evaluation));
    }
}
