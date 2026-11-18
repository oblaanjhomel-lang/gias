<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single row: Weighted Sum Model weights (0–100 each, normalised when used) and scales.
 */
#[Fillable([
    'weight_coverage', 'weight_proficiency', 'weight_proximity', 'weight_slots',
    'proximity_zero_km', 'slot_saturation', 'updated_by',
])]
class RecommendationSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_coverage' => 'integer',
            'weight_proficiency' => 'integer',
            'weight_proximity' => 'integer',
            'weight_slots' => 'integer',
            'proximity_zero_km' => 'integer',
            'slot_saturation' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
