<?php

namespace App\Notifications;

/**
 * To a registrant whose registration was returned. Email only (they can't sign in) and
 * always sent, with the reason.
 */
class RegistrationReturned extends GiasNotification
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(
            category: 'approval',
            message: "Your GIAS registration was returned: “{$reason}” Contact your OJT Coordinator or the System Development Office.",
            actionPath: '/login',
            actionLabel: 'Open GIAS',
            meta: ['reason' => $reason],
            subject: 'GIAS: your registration was returned',
        );
    }

    public function mailOnly(): bool
    {
        return true;
    }

    public function mandatoryMail(): bool
    {
        return true;
    }
}
