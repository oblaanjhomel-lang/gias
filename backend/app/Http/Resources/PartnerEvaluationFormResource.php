<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the partner's evaluation form needs: the intern, whether this type is open, and the
 * evaluation (or null if not started). Wraps EvaluationService::forPartner().
 */
class PartnerEvaluationFormResource extends JsonResource
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
            'deployment_status' => $d->status->value,
            'intern' => ['name' => $d->student->user->name, 'program' => $d->student->user->program?->code],
            'type' => $this->resource['type']->value,
            'open' => $this->resource['open'],
            'evaluation' => $this->resource['evaluation']
                ? (new EvaluationResource($this->resource['evaluation']))->resolve($request)
                : null,
        ];
    }
}
