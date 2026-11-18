<?php

namespace App\Listeners;

use App\Events\EvaluationSubmitted;
use App\Models\User;
use App\Notifications\EvaluationAwaitingReview;
use App\Services\RecipientService;

/**
 * The student's program coordinator (or every active admin when there's none).
 */
class SendEvaluationSubmittedNotification
{
    use SendsNotifications;

    public function __construct(private readonly RecipientService $recipients) {}

    public function handle(EvaluationSubmitted $event): void
    {
        $evaluation = $event->evaluation->loadMissing(['deployment.student.user', 'deployment.industryPartner']);
        $reviewers = $this->recipients->programReviewers($evaluation->deployment->student->user->program_id);

        $this->send($reviewers, fn (User $reviewer) => new EvaluationAwaitingReview(
            $evaluation,
            $this->recipients->reviewPath($reviewer, 'evaluations'),
        ));
    }
}
