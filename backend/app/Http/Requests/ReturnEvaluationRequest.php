<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route `can:review,evaluation`
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Tell the partner what to change; the note is shown above their form.',
        ];
    }
}
