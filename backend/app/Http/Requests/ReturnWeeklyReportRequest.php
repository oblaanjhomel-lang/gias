<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnWeeklyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route `can:review,report`
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
            'note.required' => 'Tell the intern what to fix; the note is sent with the report.',
        ];
    }
}
