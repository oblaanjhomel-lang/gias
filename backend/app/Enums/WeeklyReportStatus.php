<?php

namespace App\Enums;

/**
 * draft → submitted (student) → signed (partner, final) | returned (partner, with a note)
 * returned → submitted again after the student edits it.
 */
enum WeeklyReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Signed = 'signed';
    case Returned = 'returned';

    /** Statuses the student may still edit or delete. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}
