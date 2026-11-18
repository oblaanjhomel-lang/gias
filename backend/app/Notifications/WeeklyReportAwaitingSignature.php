<?php

namespace App\Notifications;

use App\Models\WeeklyReport;

/**
 * To the partner when an intern submits a weekly report.
 */
class WeeklyReportAwaitingSignature extends GiasNotification
{
    public function __construct(WeeklyReport $report)
    {
        $student = $report->deployment->student->user->name;

        parent::__construct(
            category: 'deployment',
            message: "{$student} submitted the week {$report->week_number} report (".self::hours($report->total_hours).' h). It needs your signature.',
            actionPath: '/partner/interns',
            actionLabel: 'Review report',
            meta: ['report_id' => $report->id, 'deployment_id' => $report->deployment_id],
            subject: 'GIAS: a weekly report needs your signature',
        );
    }
}
