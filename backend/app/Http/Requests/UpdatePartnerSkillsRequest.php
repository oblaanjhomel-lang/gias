<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * {skills: [{skill_id, importance: 1 preferred | 2 required}]}. An empty list clears them.
 */
class UpdatePartnerSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skills' => ['present', 'array', 'max:50'],
            'skills.*.skill_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('skills', 'id')->where('is_active', true),
            ],
            'skills.*.importance' => ['required', 'integer', Rule::in([1, 2])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'skills.*.importance.in' => 'Importance must be 1 (preferred) or 2 (required).',
            'skills.*.skill_id.exists' => 'Choose an active skill.',
        ];
    }
}
