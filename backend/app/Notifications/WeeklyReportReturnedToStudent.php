<?php

namespace App\Notifications;

use App\Models\WeeklyReport;

/**
 * To the student when the partner returns a weekly report, with the partner's note.
 */
class WeeklyReportReturnedToStudent extends GiasNotification
{
    public function __construct(WeeklyReport $report)
    {
        parent::__construct(
            category: 'deployment',
            message: "{$report->deployment->industryPartner->company_name} returned your week {$report->week_number} report: “{$report->return_note}”",
            actionPath: '/student/deployment',
            actionLabel: 'Fix my report',
            meta: ['report_id' => $report->id],
            subject: 'GIAS: your weekly report was returned',
        );
    }
}
