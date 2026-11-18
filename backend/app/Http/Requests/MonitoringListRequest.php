<?php

namespace App\Http\Requests;

use App\Enums\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * program_id and province are admin-only filters (ignored for coordinators).
 */
class MonitoringListRequest extends FormRequest
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
            'filter' => ['nullable', Rule::in(['all', 'active', 'overdue', 'completed', 'withdrawn'])],
            'search' => ['nullable', 'string', 'max:100'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'province' => ['nullable', Rule::enum(Province::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
