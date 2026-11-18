<?php

namespace App\Notifications;

use App\Models\Evaluation;

/**
 * To the program's coordinator (or the admins) when a partner submits an evaluation.
 */
class EvaluationAwaitingReview extends GiasNotification
{
    public function __construct(Evaluation $evaluation, string $reviewPath)
    {
        $deployment = $evaluation->deployment;

        parent::__construct(
            category: 'evaluation',
            message: "{$deployment->industryPartner->company_name} submitted the {$evaluation->type->value} evaluation for {$deployment->student->user->name} (average "
                .number_format((float) $evaluation->partner_mean, 2).'). It needs your review.',
            actionPath: $reviewPath,
            actionLabel: 'Review evaluation',
            meta: ['evaluation_id' => $evaluation->id],
            subject: 'GIAS: an evaluation needs your review',
        );
    }
}
