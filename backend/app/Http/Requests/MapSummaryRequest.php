<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MapSummaryRequest extends FormRequest
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
            'radius_km' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    public function radiusKm(): int
    {
        return (int) ($this->validated('radius_km') ?? config('gias.coverage_radius_km'));
    }
}
