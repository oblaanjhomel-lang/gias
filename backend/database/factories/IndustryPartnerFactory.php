<?php

namespace Database\Factories;

use App\Enums\GeocodeStatus;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Models\IndustryPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndustryPartner>
 */
class IndustryPartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(UserRole::Partner),
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'contact_number' => '09171234567',
            'address' => fake()->streetAddress(),
            'city' => 'Tagum City',
            'province' => Province::DavaoDelNorte,
            'location' => null,
            'geocode_status' => GeocodeStatus::Pending,
            'intern_slots' => 5,
        ];
    }
}
