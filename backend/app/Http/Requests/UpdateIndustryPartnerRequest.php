<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateIndustryPartnerRequest extends StoreIndustryPartnerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $partner */
        $partner = $this->route('partner');

        $rules = parent::rules();
        $box = config('gias.region_bounds');
        $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($partner->id)];

        return $rules + [
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            // Manual pin (drag-the-pin fix): both or neither, inside the Davao Region.
            'latitude' => [
                'nullable', 'required_with:longitude', 'numeric',
                'between:'.$box['min_lat'].','.$box['max_lat'],
            ],
            'longitude' => [
                'nullable', 'required_with:latitude', 'numeric',
                'between:'.$box['min_lng'].','.$box['max_lng'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'latitude.between' => 'The pin must be inside the Davao Region.',
            'longitude.between' => 'The pin must be inside the Davao Region.',
        ];
    }
}
