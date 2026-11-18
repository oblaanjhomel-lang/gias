<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        Program::create(['code' => 'BSAB', 'name' => 'BS Agribusiness']);
    }

    public function test_signed_in_users_can_list_programs(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->getJson('/api/programs')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'BSAB')
            ->assertJsonPath('data.1.code', 'BSIT')
            ->assertJsonStructure(['data' => [['id', 'code', 'name']]]);
    }

    public function test_guests_can_list_programs_for_the_registration_form(): void
    {
        $this->getJson('/api/programs')->assertOk()->assertJsonCount(2, 'data');
    }
}
