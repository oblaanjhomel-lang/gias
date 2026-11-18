<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRecommendationSettingsRequest extends FormRequest
{
    private const WEIGHTS = ['weight_coverage', 'weight_proficiency', 'weight_proximity', 'weight_slots'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_fill_keys(self::WEIGHTS, ['required', 'integer', 'between:0,100']) + [
            'proximity_zero_km' => ['required', 'integer', 'between:10,300'],
            'slot_saturation' => ['required', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if (array_sum(array_map(fn (string $k) => (int) $this->input($k), self::WEIGHTS)) === 0) {
                    $validator->errors()->add('weights', 'At least one weight must be above 0.');
                }
            },
        ];
    }
}
