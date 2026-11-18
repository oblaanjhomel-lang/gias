<?php

namespace App\Http\Resources;

use App\Models\IndustryPartner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A partner near a student's home. Expects `distance_km` and `filled` selected on the model.
 *
 * @mixin IndustryPartner
 */
class NearbyPartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'company_name' => $this->company_name,
            'city' => $this->city,
            'province' => $this->province->value,
            'distance_km' => round((float) $this->distance_km, 1),
            'intern_slots' => $this->intern_slots,
            'open' => max($this->intern_slots - (int) $this->filled, 0),
            'latitude' => $this->location?->getLatitude(),
            'longitude' => $this->location?->getLongitude(),
        ];
    }
}
