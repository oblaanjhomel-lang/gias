<?php

namespace App\Notifications;

/**
 * Scheduled: to a coordinator (or admin) with evaluations waiting more than 3 days for review.
 */
class EvaluationsAwaitingReviewReminder extends GiasNotification
{
    public function __construct(int $count, string $reviewPath)
    {
        parent::__construct(
            category: 'deadline',
            message: self::plural($count, 'evaluation').($count === 1 ? ' has' : ' have').' waited more than 3 days for your review.',
            actionPath: $reviewPath,
            actionLabel: 'Review evaluations',
            meta: ['count' => $count],
            subject: 'GIAS: evaluations are waiting for your review',
        );
    }
}
