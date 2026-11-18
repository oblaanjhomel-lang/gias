<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A deployment with progress and all its weekly reports. Built from
 * DeploymentMonitoringService::detail() / forStudent(): {deployment, progress, coordinator?, weeks?}.
 */
class DeploymentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Deployment $d */
        $d = $this->resource['deployment'];
        $partner = $d->industryPartner;
        /** @var User|null $coordinator */
        $coordinator = $this->resource['coordinator'] ?? null;

        return [
            'id' => $d->id,
            'status' => $d->status->value,
            'start_date' => $d->start_date->toDateString(),
            'end_date' => $d->end_date?->toDateString(),
            'completed_at' => $d->completed_at?->toIso8601String(),
            'completed_by' => $d->relationLoaded('completedBy') ? $d->completedBy?->name : null,
            'completion_note' => $d->completion_note,
            'student' => [
                'id' => $d->student->user_id,
                'name' => $d->student->user->name,
                'student_number' => $d->student->student_number,
                'program' => $d->student->user->program ? [
                    'code' => $d->student->user->program->code,
                    'name' => $d->student->user->program->name,
                ] : null,
            ],
            'partner' => [
                'id' => $partner->user_id,
                'company_name' => $partner->company_name,
                'address' => $partner->address,
                'city' => $partner->city,
                'province' => $partner->province->value,
                'contact_person' => $partner->contact_person,
                'contact_number' => $partner->contact_number,
            ],
            'coordinator' => $coordinator ? ['name' => $coordinator->name, 'email' => $coordinator->email] : null,
            'progress' => $this->resource['progress'],
            'reports' => WeeklyReportResource::collection($d->weeklyReports)->resolve($request),
            // Student only: weeks that have started (for the report form), with any existing report.
            'weeks' => $this->resource['weeks'] ?? null,
        ];
    }
}
