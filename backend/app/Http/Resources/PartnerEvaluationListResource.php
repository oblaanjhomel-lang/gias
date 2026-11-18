<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One active intern at the partner's site with both evaluations' state.
 * Wraps a row from EvaluationService::partnerOverview().
 */
class PartnerEvaluationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Deployment $d */
        $d = $this->resource['deployment'];

        return [
            'deployment_id' => $d->id,
            'intern' => ['name' => $d->student->user->name, 'program' => $d->student->user->program?->code],
            'signed_hours' => $this->resource['signed_hours'],
            'required_hours' => $this->resource['required_hours'],
            'midterm' => $this->resource['midterm'],
            'final' => $this->resource['final'],
        ];
    }
}
