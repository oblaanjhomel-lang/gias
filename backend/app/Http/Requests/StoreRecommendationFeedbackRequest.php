<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecommendationFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route `can:manageStudent`
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'algorithm' => ['required', Rule::in(['cos', 'wsm', 'fuzzy'])],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
