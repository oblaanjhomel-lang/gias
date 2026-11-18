<?php

namespace App\Http\Requests;

use App\Enums\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/map/partners?program_id=&province=&saturation[]=open&saturation[]=near
 */
class MapPartnersRequest extends FormRequest
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
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'province' => ['nullable', Rule::enum(Province::class)],
            'saturation' => ['nullable', 'array'],
            'saturation.*' => [Rule::in(['open', 'near', 'full', 'pending'])],
        ];
    }
}
