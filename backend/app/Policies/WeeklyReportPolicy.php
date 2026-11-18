<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WeeklyReport;

/**
 * Ownership only. The status flow (what can happen in which state) is enforced in
 * WeeklyReportService and answers 422; failing these checks answers 403.
 */
class WeeklyReportPolicy
{
    /** The student whose deployment it is. */
    public function manage(User $user, WeeklyReport $report): bool
    {
        return $user->role === UserRole::Student
            && $report->deployment->student?->user_id === $user->id;
    }

    /** The Industry Partner hosting the deployment (countersign / return / view). */
    public function review(User $user, WeeklyReport $report): bool
    {
        return $user->role === UserRole::Partner
            && $report->deployment->industryPartner?->user_id === $user->id;
    }
}
