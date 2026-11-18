<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * {skills: [{skill_id, level: 1 beginner | 2 intermediate | 3 advanced}]}. An empty list clears them.
 */
class UpdateStudentSkillsRequest extends FormRequest
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
            'skills.*.level' => ['required', 'integer', Rule::in([1, 2, 3])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'skills.*.level.in' => 'Level must be 1 (beginner), 2 (intermediate) or 3 (advanced).',
            'skills.*.skill_id.exists' => 'Choose an active skill.',
        ];
    }
}
