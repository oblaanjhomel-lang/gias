<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role middleware + UserPolicy (route `can`) already ran
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Give a reason; it is sent to the applicant.',
        ];
    }
}
