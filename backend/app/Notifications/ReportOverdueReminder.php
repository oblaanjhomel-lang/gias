<?php

namespace App\Notifications;

/**
 * Scheduled: to a student whose deployment is overdue (no report in over 14 days).
 */
class ReportOverdueReminder extends GiasNotification
{
    public function __construct(int $deploymentId)
    {
        parent::__construct(
            category: 'deadline',
            message: 'Your last weekly report was more than '.config('gias.monitoring.overdue_days').' days ago. Submit your missing weeks.',
            actionPath: '/student/deployment',
            actionLabel: 'Write a report',
            meta: ['deployment_id' => $deploymentId],
            subject: 'GIAS: your weekly reports are overdue',
        );
    }
}
