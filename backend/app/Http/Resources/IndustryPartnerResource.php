<?php

namespace App\Http\Resources;

use App\Models\IndustryPartner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps an IndustryPartner profile (with its user loaded). `id` is the USER id,
 * which is what the /api/admin/partners/{partner} routes take.
 *
 * @mixin IndustryPartner
 */
class IndustryPartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'email' => $this->user->email,
            'status' => $this->user->status->value,
            'company_name' => $this->company_name,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province->value,
            'intern_slots' => $this->intern_slots,
            'latitude' => $this->location?->getLatitude(),
            'longitude' => $this->location?->getLongitude(),
            'geocode_status' => $this->geocode_status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
