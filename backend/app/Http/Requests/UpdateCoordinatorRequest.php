<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoordinatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $coordinator */
        $coordinator = $this->route('coordinator');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255', 'ends_with:@dnsc.edu.ph',
                Rule::unique('users', 'email')->ignore($coordinator->id),
            ],
            'program_id' => ['required', 'integer', Rule::exists('programs', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.ends_with' => 'Coordinator accounts must use a @dnsc.edu.ph email address.',
            'program_id.required' => 'Select a program.',
        ];
    }
}
