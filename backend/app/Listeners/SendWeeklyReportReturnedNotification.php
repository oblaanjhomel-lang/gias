<?php

namespace App\Listeners;

use App\Events\WeeklyReportReturned;
use App\Notifications\WeeklyReportReturnedToStudent;

/**
 * The student: their report was returned with a note.
 */
class SendWeeklyReportReturnedNotification
{
    use SendsNotifications;

    public function handle(WeeklyReportReturned $event): void
    {
        $report = $event->report->loadMissing(['deployment.student.user', 'deployment.industryPartner']);

        $this->send([$report->deployment->student->user], new WeeklyReportReturnedToStudent($report));
    }
}
