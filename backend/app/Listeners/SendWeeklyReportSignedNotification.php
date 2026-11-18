<?php

namespace App\Listeners;

use App\Events\WeeklyReportSigned;
use App\Notifications\WeeklyReportSigned as WeeklyReportSignedNotification;

/**
 * The student: their report was countersigned.
 */
class SendWeeklyReportSignedNotification
{
    use SendsNotifications;

    public function handle(WeeklyReportSigned $event): void
    {
        $report = $event->report->loadMissing(['deployment.student.user', 'deployment.industryPartner']);

        $this->send([$report->deployment->student->user], new WeeklyReportSignedNotification($report));
    }
}
