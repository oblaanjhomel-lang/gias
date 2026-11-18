<?php

namespace App\Http\Requests;

use App\Services\EvaluationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partner draft: {ratings: {criterion_key: 1–5}, comment}. Ratings may be partial; submitting
 * needs all six (checked by EvaluationService).
 */
class SaveEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route `can:partnerEvaluate`
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $keys = array_keys(EvaluationService::criteria());
        $rules = [
            'ratings' => ['present', 'array:'.implode(',', $keys)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
        foreach ($keys as $key) {
            $rules["ratings.{$key}"] = ['nullable', 'integer', 'between:1,5'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ratings.*.integer' => 'Ratings are whole numbers from 1 to 5.',
            'ratings.*.between' => 'Ratings are whole numbers from 1 to 5.',
        ];
    }
}
