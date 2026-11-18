<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Student view: a partner that fits their skills (WSM). No breakdown, no contact data.
 */
class PartnerSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_intersect_key(
            $this->resource,
            array_flip(['id', 'company_name', 'city', 'province', 'distance_km', 'open', 'score']),
        );
    }
}
