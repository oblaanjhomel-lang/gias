<?php

namespace App\Enums;

/**
 * draft → submitted (partner) → reviewed (coordinator / admin)
 * submitted → returned (coordinator, with a note) → submitted again (partner)
 * reviewed → submitted (reopen; not once the deployment is completed)
 */
enum EvaluationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Reviewed = 'reviewed';

    /** The partner may still change ratings. */
    public function isEditableByPartner(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}
