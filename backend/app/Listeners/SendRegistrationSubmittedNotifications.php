<?php

namespace App\Listeners;

use App\Events\RegistrationSubmitted;
use App\Models\User;
use App\Notifications\NewRegistrationPending;
use App\Notifications\RegistrationReceived;
use App\Services\RecipientService;

/**
 * Registrant: "registration received" (email only). Reviewers: "new registration to review".
 */
class SendRegistrationSubmittedNotifications
{
    use SendsNotifications;

    public function __construct(private readonly RecipientService $recipients) {}

    public function handle(RegistrationSubmitted $event): void
    {
        $registrant = $event->user->loadMissing(['program', 'industryPartner']);
        $reviewers = $this->recipients->registrationReviewers($registrant);

        $this->send([$registrant], new RegistrationReceived($reviewers['label']));
        $this->send($reviewers['users'], fn (User $reviewer) => new NewRegistrationPending(
            $registrant,
            $this->recipients->reviewPath($reviewer, 'approvals'),
        ));
    }
}
