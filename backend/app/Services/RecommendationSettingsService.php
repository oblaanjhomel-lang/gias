<?php

namespace App\Services;

use App\Models\RecommendationSetting;
use App\Models\User;

/**
 * The single row of Weighted Sum Model settings. Validation (0–100 weights, not all zero,
 * ranges for the scales) lives in UpdateRecommendationSettingsRequest.
 */
class RecommendationSettingsService
{
    public const DEFAULTS = [
        'weight_coverage' => 40,
        'weight_proficiency' => 25,
        'weight_proximity' => 20,
        'weight_slots' => 15,
        'proximity_zero_km' => 100,
        'slot_saturation' => 5,
    ];

    public function current(): RecommendationSetting
    {
        return RecommendationSetting::query()->oldest('id')->first()
            ?? RecommendationSetting::create(self::DEFAULTS);
    }

    /**
     * The values the algorithms use.
     *
     * @return array{weight_coverage: int, weight_proficiency: int, weight_proximity: int, weight_slots: int, proximity_zero_km: int, slot_saturation: int}
     */
    public function values(): array
    {
        return array_intersect_key($this->current()->toArray(), self::DEFAULTS);
    }

    /**
     * @param  array<string, int>  $data
     */
    public function update(User $actor, array $data): RecommendationSetting
    {
        $settings = $this->current();
        $settings->fill(array_intersect_key($data, self::DEFAULTS) + ['updated_by' => $actor->id])->save();

        return $settings->load('updatedBy');
    }
}
