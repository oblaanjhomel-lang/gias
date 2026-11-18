<?php

namespace App\Notifications;

use App\Models\Deployment;

/**
 * A new placement: to the student and to the hosting partner.
 */
class StudentPlaced extends GiasNotification
{
    public static function forStudent(Deployment $deployment): self
    {
        $partner = $deployment->industryPartner;

        return new self(
            "You’ve been placed at {$partner->company_name}, {$partner->city}. Your OJT starts {$deployment->start_date->format('j M Y')}.",
            '/student/deployment',
            'Open my deployment',
            ['deployment_id' => $deployment->id],
        );
    }

    public static function forPartner(Deployment $deployment): self
    {
        $user = $deployment->student->user;
        $program = $user->program ? " ({$user->program->code})" : '';

        return new self(
            "{$user->name}{$program} will start OJT at your site on {$deployment->start_date->format('j M Y')}.",
            '/partner/interns',
            'View our interns',
            ['deployment_id' => $deployment->id],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(string $message, string $actionPath, string $actionLabel, array $meta = [])
    {
        parent::__construct('deployment', $message, $actionPath, $actionLabel, $meta, 'GIAS: new OJT placement');
    }
}
