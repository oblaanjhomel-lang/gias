<?php

namespace App\Notifications;

/**
 * To an approved registrant. Always emailed: it's how they learn they can sign in.
 */
class RegistrationApproved extends GiasNotification
{
    public function __construct()
    {
        parent::__construct(
            category: 'approval',
            message: 'Your GIAS account has been approved. You can sign in now.',
            actionPath: '/login',
            actionLabel: 'Sign in to GIAS',
            subject: 'GIAS: your account is approved',
        );
    }

    public function mandatoryMail(): bool
    {
        return true;
    }
}
