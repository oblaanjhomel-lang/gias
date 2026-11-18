<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * program_id is an admin-only filter (ignored for coordinators).
 */
class EvaluationListRequest extends FormRequest
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
            'type' => ['nullable', Rule::in(['midterm', 'final'])],
            'status' => ['nullable', Rule::in(['not_started', 'draft', 'submitted', 'returned', 'reviewed'])],
            'search' => ['nullable', 'string', 'max:100'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
