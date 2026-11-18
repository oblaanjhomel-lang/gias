<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_reach_admin_route(): void
    {
        $student = User::factory()->role(UserRole::Student)->create();

        $this->actingAs($student, 'web')
            ->getJson('/api/admin/ping')
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_admin_can_reach_admin_route(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin, 'web')
            ->getJson('/api/admin/ping')
            ->assertOk()
            ->assertExactJson(['ok' => true]);
    }

    public function test_guest_gets_401_not_403(): void
    {
        $this->getJson('/api/admin/ping')->assertUnauthorized();
    }
}
