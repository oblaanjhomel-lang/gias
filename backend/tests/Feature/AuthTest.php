<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Requests from the SPA origin get Sanctum's stateful (session) middleware.
        $this->withHeaders(['Referer' => 'http://localhost:5173', 'Origin' => 'http://localhost:5173']);
    }

    private function makeUser(UserStatus $status = UserStatus::Active, UserRole $role = UserRole::Student): User
    {
        return User::factory()
            ->role($role)
            ->status($status)
            ->create([
                'email' => 'juan@dnsc.test',
                'password' => 'secret-password',
                'program_id' => Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology'])->id,
            ]);
    }

    public function test_login_success_returns_the_user_and_starts_a_session(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'juan@dnsc.test',
            'password' => 'secret-password',
            'remember' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'juan@dnsc.test')
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.program.code', 'BSIT')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'status', 'program' => ['id', 'code', 'name'], 'last_login_at']])
            ->assertCookie(config('session.cookie'));

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_returns_422(): void
    {
        $this->makeUser();

        $this->postJson('/api/auth/login', ['email' => 'juan@dnsc.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest('web');
    }

    public function test_pending_account_returns_403(): void
    {
        $this->makeUser(UserStatus::Pending);

        $this->postJson('/api/auth/login', ['email' => 'juan@dnsc.test', 'password' => 'secret-password'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is awaiting approval.');

        $this->assertGuest('web');
    }

    public function test_inactive_account_returns_403(): void
    {
        $this->makeUser(UserStatus::Inactive);

        $this->postJson('/api/auth/login', ['email' => 'juan@dnsc.test', 'password' => 'secret-password'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account has been deactivated.');

        $this->assertGuest('web');
    }

    public function test_returned_registration_shows_the_reason(): void
    {
        $user = $this->makeUser(UserStatus::Rejected);
        $user->forceFill(['review_note' => 'Your student number does not match our records.'])->save();

        $this->postJson('/api/auth/login', ['email' => 'juan@dnsc.test', 'password' => 'secret-password'])
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Your registration was returned: Your student number does not match our records. '
                .'Contact your OJT Coordinator or the System Development Office.',
            );

        $this->assertGuest('web');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_the_current_user(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'web')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.program.code', 'BSIT');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'web')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertGuest('web');

        // Drop guard instances cached in memory so the next request is evaluated fresh.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'juan@dnsc.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'juan@dnsc.test', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }
}
