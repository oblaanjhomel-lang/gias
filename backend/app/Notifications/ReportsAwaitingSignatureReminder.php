<?php

namespace App\Notifications;

/**
 * Scheduled: to a partner with weekly reports waiting more than 3 days for a signature.
 */
class ReportsAwaitingSignatureReminder extends GiasNotification
{
    public function __construct(int $count)
    {
        parent::__construct(
            category: 'deadline',
            message: self::plural($count, 'weekly report').($count === 1 ? ' has' : ' have').' waited more than 3 days for your signature.',
            actionPath: '/partner/interns',
            actionLabel: 'Review reports',
            meta: ['count' => $count],
            subject: 'GIAS: weekly reports are waiting for your signature',
        );
    }
}
