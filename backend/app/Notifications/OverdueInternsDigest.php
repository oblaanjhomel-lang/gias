<?php

namespace App\Notifications;

/**
 * Scheduled, Mondays: to a coordinator with interns who haven't reported in over 14 days.
 */
class OverdueInternsDigest extends GiasNotification
{
    /**
     * @param  list<string>  $names  all overdue interns (up to 5 are named)
     */
    public function __construct(array $names)
    {
        $count = count($names);
        $shown = implode(', ', array_slice($names, 0, 5)).($count > 5 ? ' and '.($count - 5).' more' : '');

        parent::__construct(
            category: 'deadline',
            message: self::plural($count, 'intern').($count === 1 ? ' hasn’t' : ' haven’t').' submitted a weekly report in over '
                .config('gias.monitoring.overdue_days')." days: {$shown}.",
            actionPath: '/coordinator/monitoring',
            actionLabel: 'Open intern monitoring',
            meta: ['count' => $count, 'names' => array_slice($names, 0, 5)],
            subject: 'GIAS: weekly overdue interns digest',
        );
    }
}
