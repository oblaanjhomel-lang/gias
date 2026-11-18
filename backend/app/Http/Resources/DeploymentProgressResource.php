<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An intern at a partner's site with OJT progress (set by DeploymentMonitoringService as the
 * `progress` attribute). Name and program only.
 *
 * @mixin Deployment
 */
class DeploymentProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intern' => [
                'name' => $this->student->user->name,
                'program' => $this->student->user->program?->code,
            ],
            'start_date' => $this->start_date->toDateString(),
            'status' => $this->status->value,
            'progress' => $this->resource->getAttribute('progress'),
        ];
    }
}
