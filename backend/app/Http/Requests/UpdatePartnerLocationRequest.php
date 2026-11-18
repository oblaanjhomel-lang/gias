<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role middleware + MapService (own pin only for partners)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $box = config('gias.region_bounds');

        return [
            'latitude' => ['required', 'numeric', 'between:'.$box['min_lat'].','.$box['max_lat']],
            'longitude' => ['required', 'numeric', 'between:'.$box['min_lng'].','.$box['max_lng']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.between' => 'The pin must be inside the Davao Region.',
            'longitude.between' => 'The pin must be inside the Davao Region.',
        ];
    }
}
