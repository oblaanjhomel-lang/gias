<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\User;

/**
 * To whoever reviews a new registration (the program's coordinator, or the admins).
 */
class NewRegistrationPending extends GiasNotification
{
    public function __construct(User $registrant, string $reviewPath)
    {
        $isPartner = $registrant->role === UserRole::Partner;
        $message = $isPartner
            ? ($registrant->industryPartner?->company_name ?? $registrant->name).' registered as an Industry Partner and is waiting for approval.'
            : $registrant->name.($registrant->program ? " ({$registrant->program->code})" : '').' registered as a student and is waiting for approval.';

        parent::__construct(
            category: 'approval',
            message: $message,
            actionPath: $reviewPath,
            actionLabel: 'Review registrations',
            meta: ['user_id' => $registrant->id, 'role' => $registrant->role->value],
            subject: 'GIAS: new registration to review',
        );
    }
}
