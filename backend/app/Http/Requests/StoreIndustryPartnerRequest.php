<?php

namespace App\Http\Requests;

use App\Enums\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndustryPartnerRequest extends FormRequest
{
    /** 09XXXXXXXXX or +639XXXXXXXXX */
    public const PH_MOBILE = '/^(09\d{9}|\+639\d{9})$/';

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

        // Accept "0917 123 4567" / "0917-123-4567"; store digits only.
        if (is_string($this->input('contact_number'))) {
            $number = preg_replace('/[\s\-()]/', '', $this->input('contact_number'));
            $merge['contact_number'] = $number === '' ? null : $number;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'contact_number' => ['nullable', 'string', 'regex:'.self::PH_MOBILE],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', Rule::enum(Province::class)],
            'intern_slots' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Use a PH mobile number: 09XXXXXXXXX or +639XXXXXXXXX.',
            'province.enum' => 'Select a province in the Davao Region.',
        ];
    }
}
