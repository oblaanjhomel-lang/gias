<?php

namespace App\Notifications;

use App\Models\WeeklyReport;

/**
 * To the student when the partner countersigns a weekly report.
 */
class WeeklyReportSigned extends GiasNotification
{
    public function __construct(WeeklyReport $report)
    {
        parent::__construct(
            category: 'deployment',
            message: "{$report->deployment->industryPartner->company_name} signed your week {$report->week_number} report (".self::hours($report->total_hours).' h).',
            actionPath: '/student/deployment',
            actionLabel: 'Open my deployment',
            meta: ['report_id' => $report->id],
            subject: 'GIAS: your weekly report was signed',
        );
    }
}
