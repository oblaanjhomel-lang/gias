<?php

namespace App\Notifications;

use App\Models\Deployment;

/**
 * A deployment was completed: to the student and to the hosting partner.
 */
class DeploymentCompletedNotice extends GiasNotification
{
    public static function forStudent(Deployment $deployment): self
    {
        return new self(
            "Your OJT at {$deployment->industryPartner->company_name} is complete. Congratulations!",
            '/student/deployment',
            'Open my deployment',
            ['deployment_id' => $deployment->id],
        );
    }

    public static function forPartner(Deployment $deployment): self
    {
        return new self(
            "{$deployment->student->user->name}’s OJT at your site is complete.",
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
        parent::__construct('deployment', $message, $actionPath, $actionLabel, $meta, 'GIAS: OJT completed');
    }
}
