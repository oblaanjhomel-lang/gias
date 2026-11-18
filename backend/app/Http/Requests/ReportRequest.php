<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

/**
 * The partner directory is admin-only (403 for coordinators).
 */
class ReportRequest extends AnalyticsFilterRequest
{
    public function authorize(): bool
    {
        return $this->route('type') !== 'partners' || $this->user()?->role === UserRole::Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ];
    }
}
