<?php

namespace App\Http\Resources;

use App\Models\Skill;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student in the "awaiting placement" queue. `id` is the USER id.
 *
 * @mixin Student
 */
class PlacementStudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deployment = $this->activeDeployment;

        return [
            'id' => $this->user_id,
            'name' => $this->user->name,
            'program' => $this->user->program?->code,
            'city' => $this->city,
            'top_skills' => $this->skills->take(3)->map(fn (Skill $s) => [
                'name' => $s->name,
                'level' => (int) $s->pivot->level,
            ])->values()->all(),
            'skills_count' => $this->skills->count(),
            'placement' => $deployment ? [
                'deployment_id' => $deployment->id,
                'partner_id' => $deployment->industryPartner->user_id,
                'company_name' => $deployment->industryPartner->company_name,
            ] : null,
        ];
    }
}
