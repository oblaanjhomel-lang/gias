<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * {items: [{student_id, partner_id, algorithm?, start_date}]}. All-or-nothing.
 */
class PlaceBatchRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.student_id' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('role', 'student')],
            'items.*.partner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'partner')],
            'items.*.algorithm' => ['nullable', Rule::in(['cos', 'wsm', 'fuzzy', 'manual'])],
            'items.*.start_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.student_id.distinct' => 'A student appears more than once in this batch.',
        ];
    }
}
