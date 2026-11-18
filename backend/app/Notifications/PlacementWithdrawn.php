<?php

namespace App\Notifications;

use App\Models\Deployment;

/**
 * A placement was withdrawn: to the student and to the hosting partner.
 */
class PlacementWithdrawn extends GiasNotification
{
    public static function forStudent(Deployment $deployment): self
    {
        return new self(
            "Your placement at {$deployment->industryPartner->company_name} was withdrawn. Your OJT Coordinator will contact you about next steps.",
            '/student/deployment',
            'Open my deployment',
            ['deployment_id' => $deployment->id],
        );
    }

    public static function forPartner(Deployment $deployment): self
    {
        return new self(
            "{$deployment->student->user->name}’s placement at your site was withdrawn.",
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
        parent::__construct('deployment', $message, $actionPath, $actionLabel, $meta, 'GIAS: placement withdrawn');
    }
}
