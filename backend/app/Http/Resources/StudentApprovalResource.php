<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student registration: the user (with `student` and `program` loaded).
 *
 * @mixin User
 */
class StudentApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->student;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'student_number' => $profile?->student_number,
            'year_level' => $profile?->year_level,
            'program' => $this->program ? new ProgramResource($this->program) : null,
            'contact_number' => $profile?->contact_number,
            'address' => $profile?->address,
            'city' => $profile?->city,
            'province' => $profile?->province?->value,
            'latitude' => $profile?->home_location?->getLatitude(),
            'longitude' => $profile?->home_location?->getLongitude(),
            'geocode_status' => $profile?->geocode_status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => $this->review_note,
        ];
    }
}
