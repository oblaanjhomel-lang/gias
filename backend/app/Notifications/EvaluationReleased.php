<?php

namespace App\Notifications;

use App\Models\Evaluation;
use App\Services\EvaluationService;

/**
 * To the student when their FINAL evaluation is reviewed (the final OJT rating is released).
 */
class EvaluationReleased extends GiasNotification
{
    public function __construct(Evaluation $evaluation)
    {
        $final = number_format((float) $evaluation->final_rating, 2);
        $descriptor = EvaluationService::descriptor($evaluation->final_rating);

        parent::__construct(
            category: 'evaluation',
            message: "Your final OJT rating is {$final} ({$descriptor}).",
            actionPath: '/student/evaluation',
            actionLabel: 'See my evaluation',
            meta: ['evaluation_id' => $evaluation->id, 'final_rating' => (float) $evaluation->final_rating],
            subject: 'GIAS: your final OJT rating is out',
        );
    }
}
