<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME') ?: 'System Administrator';
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        foreach (['ADMIN_EMAIL' => $email, 'ADMIN_PASSWORD' => $password] as $key => $value) {
            if (blank($value) || $value === 'CHANGE_ME') {
                throw new RuntimeException("Set {$key} in backend/.env before seeding the admin account.");
            }
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password, // hashed by the model cast
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'program_id' => null,
            ],
        );
    }
}
