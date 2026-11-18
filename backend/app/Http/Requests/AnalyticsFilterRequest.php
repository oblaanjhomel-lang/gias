<?php

namespace App\Http\Requests;

use App\Enums\Province;
use App\Support\AcademicTerm;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters shared by /api/analytics/overview and /api/reports/{type}. program_id is ignored
 * for coordinators (always their own program).
 */
class AnalyticsFilterRequest extends FormRequest
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
            'term' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_string($value) || ! AcademicTerm::isValidKey($value)) {
                    $fail('The term must look like 2026-2027-1.');
                }
            }],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'province' => ['nullable', Rule::enum(Province::class)],
        ];
    }

    /**
     * @return array{term?: string|null, program_id?: int|null, province?: string|null}
     */
    public function filters(): array
    {
        return $this->safe()->only(['term', 'program_id', 'province']);
    }
}
