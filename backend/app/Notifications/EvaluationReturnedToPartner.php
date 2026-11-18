<?php

namespace App\Notifications;

use App\Models\Evaluation;

/**
 * To the partner when the coordinator returns an evaluation, with the note.
 */
class EvaluationReturnedToPartner extends GiasNotification
{
    public function __construct(Evaluation $evaluation)
    {
        parent::__construct(
            category: 'evaluation',
            message: "Your {$evaluation->type->value} evaluation for {$evaluation->deployment->student->user->name} was returned: “{$evaluation->return_note}”",
            actionPath: '/partner/evaluations',
            actionLabel: 'Edit evaluation',
            meta: ['evaluation_id' => $evaluation->id],
            subject: 'GIAS: an evaluation was returned to you',
        );
    }
}
