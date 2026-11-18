<?php

namespace App\Listeners;

use App\Events\WeeklyReportSubmitted;
use App\Notifications\WeeklyReportAwaitingSignature;

/**
 * The hosting partner: a report needs their signature.
 */
class SendWeeklyReportSubmittedNotification
{
    use SendsNotifications;

    public function handle(WeeklyReportSubmitted $event): void
    {
        $report = $event->report->loadMissing(['deployment.student.user', 'deployment.industryPartner.user']);

        $this->send([$report->deployment->industryPartner->user], new WeeklyReportAwaitingSignature($report));
    }
}
