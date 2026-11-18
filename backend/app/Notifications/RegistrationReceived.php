<?php

namespace App\Notifications;

/**
 * To a new registrant. Email only: they can't sign in until approved.
 */
class RegistrationReceived extends GiasNotification
{
    /**
     * @param  string  $reviewer  "your program's OJT Coordinator" or "the GIAS Admin"
     */
    public function __construct(public readonly string $reviewer)
    {
        parent::__construct(
            category: 'approval',
            message: 'We received your GIAS registration. '.ucfirst($reviewer).' will review it, and we’ll email you the result.',
            actionPath: '/login',
            actionLabel: 'Open GIAS',
            meta: ['reviewer' => $reviewer],
            subject: 'GIAS: registration received',
        );
    }

    public function mailOnly(): bool
    {
        return true;
    }
}
