<?php

namespace Database\Factories;

use App\Enums\GeocodeStatus;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(UserRole::Student)->status(UserStatus::Pending),
            'student_number' => fake()->unique()->numerify('2023-#####'),
            'year_level' => 3,
            'contact_number' => '09171234567',
            'address' => fake()->streetAddress(),
            'city' => 'Tagum City',
            'province' => Province::DavaoDelNorte,
            'home_location' => null,
            'geocode_status' => GeocodeStatus::Failed,
        ];
    }
}
