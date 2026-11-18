<?php

namespace App\Http\Requests;

use App\Enums\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('email'))) {
            $merge['email'] = mb_strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('contact_number'))) {
            $merge['contact_number'] = preg_replace('/[\s\-()]/', '', $this->input('contact_number'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Account
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            // Academic
            'student_number' => ['required', 'string', 'max:20', Rule::unique('students', 'student_number')],
            'program_id' => ['required', 'integer', Rule::exists('programs', 'id')],
            'year_level' => ['required', 'integer', 'between:1,5'],
            // Home address
            'contact_number' => ['required', 'string', 'regex:'.StoreIndustryPartnerRequest::PH_MOBILE],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', Rule::enum(Province::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'student_number.unique' => 'This student number is already registered.',
            'program_id.required' => 'Select your program.',
            'contact_number.regex' => 'Use a PH mobile number: 09XXXXXXXXX or +639XXXXXXXXX.',
            'province.enum' => 'Select a province in the Davao Region.',
        ];
    }
}
