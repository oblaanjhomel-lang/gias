<?php

namespace App\Http\Requests;

use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename and/or (de)activate. Send either field or both.
 */
class UpdateSkillRequest extends FormRequest
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
        /** @var Skill $skill */
        $skill = $this->route('skill');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('skills', 'name')->ignore($skill->id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
