<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the coordinator / admin monitoring table. `progress` is set by the service.
 *
 * @mixin Deployment
 */
class MonitoringRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => [
                'id' => $this->student->user_id,
                'name' => $this->student->user->name,
                'student_number' => $this->student->student_number,
                'program' => $this->student->user->program?->code,
            ],
            'partner' => [
                'id' => $this->industryPartner->user_id,
                'company_name' => $this->industryPartner->company_name,
                'city' => $this->industryPartner->city,
            ],
            'start_date' => $this->start_date->toDateString(),
            'status' => $this->status->value,
            'progress' => $this->resource->getAttribute('progress'),
        ];
    }
}
