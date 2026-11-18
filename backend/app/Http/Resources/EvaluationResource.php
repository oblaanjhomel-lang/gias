<?php

namespace App\Http\Resources;

use App\Enums\EvaluationStatus;
use App\Enums\UserRole;
use App\Models\Evaluation;
use App\Services\EvaluationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Role-aware:
 *  - student:     reviewed results only; never the coordinator's note or the return note
 *  - partner:     their own ratings and the return note; not the coordinator's rating or note
 *  - coordinator / admin: everything, except a draft's ratings and comment (only its status)
 *
 * @mixin Evaluation
 */
class EvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $request->user()?->role;
        $isReviewer = in_array($role, [UserRole::Admin, UserRole::Coordinator], true);
        $hideContent = $isReviewer && $this->status === EvaluationStatus::Draft;

        $data = [
            'id' => $this->id,
            'deployment_id' => $this->deployment_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'ratings' => $hideContent ? [] : $this->ratingRows(),
            'rated_count' => count($this->ratings ?? []),
            'partner_mean' => $this->partner_mean === null ? null : (float) $this->partner_mean,
            'partner_descriptor' => EvaluationService::descriptor($this->partner_mean),
            'comment' => $hideContent ? null : $this->comment,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];

        if ($role === UserRole::Student) {
            return $data + [
                'coordinator_rating' => $this->coordinator_rating,
                'final_rating' => $this->final_rating === null ? null : (float) $this->final_rating,
                'final_descriptor' => EvaluationService::descriptor($this->final_rating),
            ];
        }

        if ($role === UserRole::Partner) {
            return $data + ['return_note' => $this->return_note];
        }

        $deployment = $this->relationLoaded('deployment') ? $this->deployment : null;

        return $data + [
            'coordinator_rating' => $this->coordinator_rating,
            'coordinator_note' => $this->coordinator_note,
            'final_rating' => $this->final_rating === null ? null : (float) $this->final_rating,
            'final_descriptor' => EvaluationService::descriptor($this->final_rating),
            'return_note' => $this->return_note,
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy?->name),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'deployment' => $deployment && $deployment->relationLoaded('student') ? [
                'id' => $deployment->id,
                'status' => $deployment->status->value,
                'student' => [
                    'name' => $deployment->student->user->name,
                    'student_number' => $deployment->student->student_number,
                    'program' => $deployment->student->user->program?->code,
                ],
                'partner' => ['company_name' => $deployment->industryPartner->company_name],
                'progress' => $deployment->getAttribute('progress'),
            ] : null,
        ];
    }

    /**
     * Criteria in order with labels (the snapshot taken at submit, or the current config).
     *
     * @return list<array{key: string, label: string, value: int|null}>
     */
    private function ratingRows(): array
    {
        $labels = $this->criteria_snapshot
            ? collect($this->criteria_snapshot)->pluck('label', 'key')->all()
            : EvaluationService::criteria();

        $rows = [];
        foreach ($labels as $key => $label) {
            $rows[] = ['key' => $key, 'label' => $label, 'value' => $this->ratings[$key] ?? null];
        }

        return $rows;
    }
}
