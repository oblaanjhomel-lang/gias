<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\IndustryPartner;
use App\Models\Program;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Program $bsit;

    private Program $bsab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $this->bsab = Program::create(['code' => 'BSAB', 'name' => 'BS Agribusiness']);
    }

    private function coordinator(Program $program, UserStatus $status = UserStatus::Active, array $attributes = []): User
    {
        return User::factory()->role(UserRole::Coordinator)->status($status)
            ->create(['program_id' => $program->id] + $attributes);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'index' => ['GET', '/api/admin/coordinators'],
            'store' => ['POST', '/api/admin/coordinators'],
            'update' => ['PUT', '/api/admin/coordinators/{id}'],
            'status' => ['PATCH', '/api/admin/coordinators/{id}/status'],
            'destroy' => ['DELETE', '/api/admin/coordinators/{id}'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_non_admins_get_403(string $method, string $uri): void
    {
        $target = $this->coordinator($this->bsit);
        $uri = str_replace('{id}', (string) $target->id, $uri);

        foreach ([UserRole::Coordinator, UserRole::Partner, UserRole::Student] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAs(User::factory()->role($role)->create(), 'web')
                ->json($method, $uri, ['status' => 'inactive'])
                ->assertForbidden();
        }

        $this->assertModelExists($target);
    }

    public function test_admin_creates_a_coordinator_and_a_set_password_link_is_sent(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin, 'web')->postJson('/api/admin/coordinators', [
            'name' => 'Maria Santos',
            'email' => 'Maria.Santos@DNSC.edu.ph',
            'program_id' => $this->bsit->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Maria Santos')
            ->assertJsonPath('data.email', 'maria.santos@dnsc.edu.ph')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.program.code', 'BSIT')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status', 'program', 'last_login_at', 'created_at']]);

        $user = User::where('email', 'maria.santos@dnsc.edu.ph')->firstOrFail();
        $this->assertSame(UserRole::Coordinator, $user->role);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost:5173/reset-password?token=')
                && str_contains($url, 'email=maria.santos%40dnsc.edu.ph');
        });
    }

    public function test_email_must_be_a_dnsc_address(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/admin/coordinators', [
            'name' => 'Outsider',
            'email' => 'someone@gmail.com',
            'program_id' => $this->bsit->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => '@dnsc.edu.ph']);
    }

    public function test_email_must_be_unique(): void
    {
        $this->coordinator($this->bsab, attributes: ['email' => 'taken@dnsc.edu.ph']);

        $this->actingAs($this->admin, 'web')->postJson('/api/admin/coordinators', [
            'name' => 'Duplicate',
            'email' => 'taken@dnsc.edu.ph',
            'program_id' => $this->bsit->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_a_program_can_have_only_one_active_coordinator_on_create(): void
    {
        $this->coordinator($this->bsit);

        $this->actingAs($this->admin, 'web')->postJson('/api/admin/coordinators', [
            'name' => 'Second Coordinator',
            'email' => 'second@dnsc.edu.ph',
            'program_id' => $this->bsit->id,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'program_id' => 'BS Information Technology already has an active coordinator. Deactivate that account first.',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'second@dnsc.edu.ph']);
    }

    public function test_a_program_can_have_only_one_active_coordinator_on_activate(): void
    {
        $this->coordinator($this->bsit);
        $inactive = $this->coordinator($this->bsit, UserStatus::Inactive);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/admin/coordinators/{$inactive->id}/status", ['status' => 'active'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['program_id' => 'already has an active coordinator']);

        $this->assertSame(UserStatus::Inactive, $inactive->fresh()->status);
    }

    public function test_moving_an_active_coordinator_into_a_taken_program_is_rejected(): void
    {
        $this->coordinator($this->bsit);
        $other = $this->coordinator($this->bsab);

        $this->actingAs($this->admin, 'web')->putJson("/api/admin/coordinators/{$other->id}", [
            'name' => $other->name,
            'email' => 'other@dnsc.edu.ph',
            'program_id' => $this->bsit->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('program_id');
    }

    public function test_admin_updates_a_coordinator(): void
    {
        $coordinator = $this->coordinator($this->bsit);

        $this->actingAs($this->admin, 'web')->putJson("/api/admin/coordinators/{$coordinator->id}", [
            'name' => 'Renamed Person',
            'email' => 'renamed@dnsc.edu.ph',
            'program_id' => $this->bsab->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed Person')
            ->assertJsonPath('data.program.code', 'BSAB');

        $this->assertDatabaseHas('users', [
            'id' => $coordinator->id,
            'email' => 'renamed@dnsc.edu.ph',
            'program_id' => $this->bsab->id,
        ]);
    }

    public function test_admin_deactivates_and_reactivates_a_coordinator(): void
    {
        $coordinator = $this->coordinator($this->bsit);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/admin/coordinators/{$coordinator->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->patchJson("/api/admin/coordinators/{$coordinator->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_deactivating_signs_the_user_out_by_deleting_their_sessions(): void
    {
        $coordinator = $this->coordinator($this->bsit);
        $bystander = $this->coordinator($this->bsab);

        foreach ([$coordinator, $bystander] as $user) {
            DB::table('sessions')->insert([
                'id' => 'session-'.$user->id,
                'user_id' => $user->id,
                'payload' => '',
                'last_activity' => time(),
            ]);
        }

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/admin/coordinators/{$coordinator->id}/status", ['status' => 'inactive'])
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $coordinator->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $bystander->id]);
    }

    public function test_admin_deletes_a_coordinator(): void
    {
        $coordinator = $this->coordinator($this->bsit);
        Password::broker()->createToken($coordinator);

        $this->actingAs($this->admin, 'web')
            ->deleteJson("/api/admin/coordinators/{$coordinator->id}")
            ->assertNoContent();

        $this->assertModelMissing($coordinator);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $coordinator->email]);
    }

    public function test_programs_without_an_active_coordinator_are_listed_in_meta(): void
    {
        $this->coordinator($this->bsit);
        $this->coordinator($this->bsab, UserStatus::Inactive); // inactive doesn't count
        $bsft = Program::create(['code' => 'BSFT', 'name' => 'BS Food Technology']);

        $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/coordinators')
            ->assertOk()
            ->assertJsonPath('meta.programs_without_active_coordinator', [
                ['id' => $this->bsab->id, 'code' => 'BSAB', 'name' => 'BS Agribusiness', 'required_ojt_hours' => 486],
                ['id' => $bsft->id, 'code' => 'BSFT', 'name' => 'BS Food Technology', 'required_ojt_hours' => 486],
            ])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_search_and_status_filter(): void
    {
        $this->coordinator($this->bsit, attributes: ['name' => 'Ana Reyes', 'email' => 'ana@dnsc.edu.ph']);
        $this->coordinator($this->bsab, attributes: ['name' => 'Ben Cruz', 'email' => 'ben@dnsc.edu.ph']);
        $this->coordinator($this->bsab, UserStatus::Inactive, ['name' => 'Carl Lim', 'email' => 'carl@dnsc.edu.ph']);

        $this->actingAs($this->admin, 'web');

        $names = fn (string $query) => collect($this->getJson('/api/admin/coordinators?'.$query)->assertOk()->json('data'))
            ->pluck('name')->all();

        $this->assertSame(['Ana Reyes'], $names('search=REYES'));                 // name, case-insensitive
        $this->assertSame(['Ben Cruz'], $names('search=ben%40dnsc'));             // email
        $this->assertSame(['Ben Cruz', 'Carl Lim'], $names('search=agribusiness')); // program name
        $this->assertSame(['Ana Reyes'], $names('search=bsit'));                  // program code
        $this->assertSame(['Carl Lim'], $names('status=inactive'));
        $this->assertSame(['Ben Cruz'], $names('search=bsab&status=active'));
        $this->assertSame([], $names('search=100%25'));                            // LIKE wildcard is literal
    }

    public function test_routes_only_resolve_coordinators(): void
    {
        $partner = IndustryPartner::factory()->create()->user;

        $this->actingAs($this->admin, 'web');

        $this->deleteJson("/api/admin/coordinators/{$partner->id}")->assertNotFound();
        $this->patchJson("/api/admin/coordinators/{$this->admin->id}/status", ['status' => 'inactive'])->assertNotFound();
        $this->deleteJson('/api/admin/coordinators/not-a-number')->assertNotFound();

        $this->assertModelExists($partner);
        $this->assertSame(UserStatus::Active, $this->admin->fresh()->status);
    }
}
