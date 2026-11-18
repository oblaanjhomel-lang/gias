<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A deployment in the coordinator's evaluation queue with its evaluation of the listed type
 * (`status` is "not_started" when there's none). Drafts show their status only.
 *
 * @mixin Deployment
 */
class EvaluationQueueRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Evaluation|null $evaluation */
        $evaluation = $this->relationLoaded('final') ? $this->final : ($this->relationLoaded('midterm') ? $this->midterm : null);
        $submittedOrLater = $evaluation && $evaluation->status->value !== 'draft';

        return [
            'deployment_id' => $this->id,
            'deployment_status' => $this->status->value,
            'student' => [
                'name' => $this->student->user->name,
                'student_number' => $this->student->student_number,
                'program' => $this->student->user->program?->code,
            ],
            'partner' => ['company_name' => $this->industryPartner->company_name],
            'progress' => $this->resource->getAttribute('progress'),
            'evaluation' => [
                'id' => $evaluation?->id,
                'status' => $evaluation?->status->value ?? 'not_started',
                'partner_mean' => $submittedOrLater && $evaluation->partner_mean !== null ? (float) $evaluation->partner_mean : null,
                'final_rating' => $evaluation?->final_rating === null ? null : (float) $evaluation->final_rating,
            ],
        ];
    }
}
