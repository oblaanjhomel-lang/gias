<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * {student_id, partner_id, algorithm?, start_date}. Ids are USER ids.
 * Scope (own program for coordinators) is checked in PlacementService via UserPolicy.
 */
class PlaceStudentRequest extends FormRequest
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
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            'partner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'partner')],
            'algorithm' => ['nullable', Rule::in(['cos', 'wsm', 'fuzzy', 'manual'])],
            'start_date' => ['required', 'date'],
        ];
    }
}
