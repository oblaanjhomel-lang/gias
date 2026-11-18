<?php

namespace App\Listeners;

use App\Enums\EvaluationType;
use App\Events\EvaluationReviewed;
use App\Notifications\EvaluationReleased;

/**
 * The student, for FINAL evaluations only: their final OJT rating is released.
 */
class SendEvaluationReviewedNotification
{
    use SendsNotifications;

    public function handle(EvaluationReviewed $event): void
    {
        if ($event->evaluation->type !== EvaluationType::Final) {
            return;
        }

        $evaluation = $event->evaluation->loadMissing('deployment.student.user');

        $this->send([$evaluation->deployment->student->user], new EvaluationReleased($evaluation));
    }
}
