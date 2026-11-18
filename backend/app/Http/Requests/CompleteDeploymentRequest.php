<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * {force?, note?}. Forcing (completing below the required hours) is admin-only and needs a
 * reason; the service enforces the role.
 */
class CompleteDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route `can:complete,deployment`
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'force' => ['sometimes', 'boolean'],
            'note' => [Rule::requiredIf(fn () => $this->boolean('force')), 'nullable', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Give a reason for completing below the required hours.',
        ];
    }
}
