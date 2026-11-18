<?php

namespace App\Notifications;

/**
 * Scheduled: to a partner when an intern's midterm or final evaluation opens.
 */
class EvaluationWindowOpened extends GiasNotification
{
    public function __construct(string $studentName, string $type, int $deploymentId)
    {
        parent::__construct(
            category: 'evaluation',
            message: "The {$type} evaluation for {$studentName} is now open.",
            actionPath: '/partner/evaluations',
            actionLabel: 'Evaluate intern',
            meta: ['deployment_id' => $deploymentId, 'type' => $type],
            subject: "GIAS: {$type} evaluation open",
        );
    }
}
