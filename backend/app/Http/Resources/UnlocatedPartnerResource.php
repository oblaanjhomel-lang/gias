<?php

namespace App\Http\Resources;

use App\Models\IndustryPartner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A partner whose address could not be placed on the map. `id` is the user id.
 *
 * @mixin IndustryPartner
 */
class UnlocatedPartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'company_name' => $this->company_name,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province->value,
            'status' => $this->user->status->value,
            'geocode_status' => $this->geocode_status->value,
        ];
    }
}
