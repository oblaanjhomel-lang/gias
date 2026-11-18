<?php

namespace App\Http\Resources;

use App\Models\RecommendationSetting;
use App\Services\RecommendationSettingsService;
use App\Services\WeightedSumService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WSM settings (editable) plus the fuzzy configuration (read-only, from config/gias.php).
 *
 * @mixin RecommendationSetting
 */
class RecommendationSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $values = array_intersect_key($this->resource->toArray(), RecommendationSettingsService::DEFAULTS);

        return $values + [
            'normalised_weights' => array_map(
                fn (float $w) => round($w, 4),
                app(WeightedSumService::class)->normalisedWeights($values),
            ),
            'defaults' => RecommendationSettingsService::DEFAULTS,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_by' => $this->updatedBy?->name,
            'fuzzy' => config('gias.fuzzy'),
        ];
    }
}
