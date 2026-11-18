<?php

namespace App\Http\Resources;

use App\Models\WeeklyReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WeeklyReport
 */
class WeeklyReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deployment_id' => $this->deployment_id,
            'week_number' => $this->week_number,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'daily_hours' => array_map('floatval', $this->daily_hours ?? []),
            'total_hours' => (float) $this->total_hours,
            'tasks' => $this->tasks,
            'learnings' => $this->learnings,
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signed_by' => $this->whenLoaded('signedBy', fn () => $this->signedBy?->name),
            'return_note' => $this->return_note,
            // Partner lists: who wrote it (name and program only).
            'intern' => $this->whenLoaded('deployment', fn () => $this->deployment->relationLoaded('student') ? [
                'name' => $this->deployment->student->user->name,
                'program' => $this->deployment->student->user->program?->code,
            ] : null),
        ];
    }
}
