<?php

namespace Tests\Feature;

use App\Enums\GeocodeStatus;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Deployment;
use App\Models\IndustryPartner;
use App\Models\Student;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndustryPartnerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        Notification::fake();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Tagum Agri Ventures Inc.',
            'contact_person' => 'Liza Mercado',
            'email' => 'hr@tagumagri.ph',
            'contact_number' => '0917 123 4567',
            'address' => 'Purok 3, Magugpo Poblacion',
            'city' => 'Tagum City',
            'province' => 'Davao del Norte',
            'intern_slots' => 4,
        ], $overrides);
    }

    private function fakeNominatimHit(float $lat = 7.4478, float $lng = 125.8078): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => (string) $lat, 'lon' => (string) $lng, 'display_name' => 'Tagum City, Davao del Norte'],
        ])]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'index' => ['GET', '/api/admin/partners'],
            'store' => ['POST', '/api/admin/partners'],
            'update' => ['PUT', '/api/admin/partners/{id}'],
            'status' => ['PATCH', '/api/admin/partners/{id}/status'],
            'destroy' => ['DELETE', '/api/admin/partners/{id}'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_non_admins_get_403(string $method, string $uri): void
    {
        $partner = IndustryPartner::factory()->create();
        $uri = str_replace('{id}', (string) $partner->user_id, $uri);

        foreach ([UserRole::Coordinator, UserRole::Partner, UserRole::Student] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAs(User::factory()->role($role)->create(), 'web')
                ->json($method, $uri, $this->payload(['status' => 'inactive']))
                ->assertForbidden();
        }

        $this->assertModelExists($partner);
    }

    public function test_admin_creates_a_partner_which_is_geocoded(): void
    {
        $this->fakeNominatimHit();

        $response = $this->actingAs($this->admin, 'web')->postJson('/api/admin/partners', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.company_name', 'Tagum Agri Ventures Inc.')
            ->assertJsonPath('data.email', 'hr@tagumagri.ph')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.contact_number', '09171234567')
            ->assertJsonPath('data.province', 'Davao del Norte')
            ->assertJsonPath('data.geocode_status', 'located')
            ->assertJsonStructure(['data' => [
                'id', 'email', 'status', 'company_name', 'contact_person', 'contact_number', 'address', 'city',
                'province', 'intern_slots', 'latitude', 'longitude', 'geocode_status', 'created_at',
            ]]);

        $this->assertEqualsWithDelta(7.4478, $response->json('data.latitude'), 0.000001);
        $this->assertEqualsWithDelta(125.8078, $response->json('data.longitude'), 0.000001);

        $user = User::where('email', 'hr@tagumagri.ph')->firstOrFail();
        $this->assertSame($user->id, $response->json('data.id'));
        $this->assertSame(UserRole::Partner, $user->role);
        $this->assertSame('Liza Mercado', $user->name);

        // Stored as a PostGIS point, SRID 4326.
        $this->assertSame(
            'POINT(125.8078 7.4478)|4326',
            DB::scalar('select ST_AsText(location) || \'|\' || ST_SRID(location) from industry_partners where user_id = ?', [$user->id]),
        );

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search')
            && $request['q'] === 'Purok 3, Magugpo Poblacion, Tagum City, Davao del Norte, Philippines'
            && $request['format'] === 'jsonv2'
            && $request['countrycodes'] === 'ph'
            && $request['viewbox'] === '125,8,126.6,5.5'
            && (int) $request['bounded'] === 1
            && str_starts_with($request->header('User-Agent')[0] ?? '', 'GIAS/1.0 ('));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_address_that_cannot_be_found_still_creates_the_account(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->actingAs($this->admin, 'web')->postJson('/api/admin/partners', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.geocode_status', 'failed')
            ->assertJsonPath('data.latitude', null)
            ->assertJsonPath('data.longitude', null);

        $this->assertDatabaseHas('users', ['email' => 'hr@tagumagri.ph', 'role' => 'partner']);
    }

    public function test_validation_rules(): void
    {
        IndustryPartner::factory()->create()->user->update(['email' => 'taken@company.ph']);

        $this->actingAs($this->admin, 'web')->postJson('/api/admin/partners', $this->payload([
            'email' => 'taken@company.ph',
            'province' => 'Cebu',
            'contact_number' => '12345',
            'intern_slots' => 101,
            'company_name' => '',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'email', 'province', 'contact_number', 'intern_slots', 'company_name',
        ]);

        Http::assertNothingSent();
    }

    public function test_plus_63_numbers_are_accepted(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/admin/partners', $this->payload(['contact_number' => '+639171234567']))
            ->assertCreated()
            ->assertJsonPath('data.contact_number', '+639171234567');
    }

    public function test_manual_coordinates_are_saved_as_a_manual_pin(): void
    {
        $partner = IndustryPartner::factory()->create(['geocode_status' => GeocodeStatus::Failed]);

        $this->actingAs($this->admin, 'web')->putJson("/api/admin/partners/{$partner->user_id}", $this->payload([
            'email' => $partner->user->email,
            'address' => 'New street', // changed, but manual coordinates win: no geocoding call
            'latitude' => 7.3125,
            'longitude' => 125.6847,
        ]))->assertOk()
            ->assertJsonPath('data.geocode_status', 'manual')
            ->assertJsonPath('data.latitude', 7.3125)
            ->assertJsonPath('data.longitude', 125.6847);

        Http::assertNothingSent();
    }

    public function test_manual_coordinates_must_be_inside_the_davao_region(): void
    {
        $partner = IndustryPartner::factory()->create();

        $this->actingAs($this->admin, 'web')->putJson("/api/admin/partners/{$partner->user_id}", $this->payload([
            'email' => $partner->user->email,
            'latitude' => 10.3157, // Cebu
            'longitude' => 123.8854,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->actingAs($this->admin, 'web')->putJson("/api/admin/partners/{$partner->user_id}", $this->payload([
            'email' => $partner->user->email,
            'latitude' => 7.3,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['longitude']);
    }

    public function test_update_regeocodes_only_when_the_address_changes(): void
    {
        $this->fakeNominatimHit(7.0731, 125.6128);
        $partner = IndustryPartner::factory()->create([
            'address' => 'Purok 3, Magugpo Poblacion',
            'city' => 'Tagum City',
            'province' => Province::DavaoDelNorte,
            'location' => Point::makeGeodetic(7.4478, 125.8078),
            'geocode_status' => GeocodeStatus::Located,
        ]);
        $this->actingAs($this->admin, 'web');

        // Same address, other fields changed: no lookup.
        $this->putJson("/api/admin/partners/{$partner->user_id}", $this->payload([
            'email' => $partner->user->email,
            'company_name' => 'Renamed Company',
            'intern_slots' => 9,
        ]))->assertOk()
            ->assertJsonPath('data.company_name', 'Renamed Company')
            ->assertJsonPath('data.intern_slots', 9)
            ->assertJsonPath('data.latitude', 7.4478);
        Http::assertNothingSent();

        // City/province changed: looked up again.
        $this->putJson("/api/admin/partners/{$partner->user_id}", $this->payload([
            'email' => $partner->user->email,
            'city' => 'Davao City',
            'province' => 'Davao City',
        ]))->assertOk()
            ->assertJsonPath('data.geocode_status', 'located')
            ->assertJsonPath('data.latitude', 7.0731);
        Http::assertSentCount(1);
    }

    public function test_admin_deactivates_activates_and_deletes_a_partner(): void
    {
        $partner = IndustryPartner::factory()->create();
        $user = $partner->user;
        DB::table('sessions')->insert(['id' => 'p-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin, 'web');

        $this->patchJson("/api/admin/partners/{$user->id}/status", ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);

        $this->patchJson("/api/admin/partners/{$user->id}/status", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->deleteJson("/api/admin/partners/{$user->id}")->assertNoContent();
        $this->assertModelMissing($user);
        $this->assertModelMissing($partner); // cascaded
    }

    public function test_search_and_status_filter(): void
    {
        $make = fn (string $company, string $city, Province $province, UserStatus $status, string $email) => IndustryPartner::factory()
            ->for(User::factory()->role(UserRole::Partner)->status($status)->state(['email' => $email]))
            ->create(['company_name' => $company, 'city' => $city, 'province' => $province, 'contact_person' => 'Pat '.$company]);

        $make('Alpha Foods', 'Tagum City', Province::DavaoDelNorte, UserStatus::Active, 'alpha@foods.ph');
        $make('Bravo Tech', 'Davao City', Province::DavaoCity, UserStatus::Pending, 'hello@bravo.ph');
        $make('Charlie Farms', 'Mati City', Province::DavaoOriental, UserStatus::Inactive, 'farm@charlie.ph');

        $this->actingAs($this->admin, 'web');

        $companies = fn (string $query) => collect($this->getJson('/api/admin/partners?'.$query)->assertOk()->json('data'))
            ->pluck('company_name')->all();

        $this->assertSame(['Alpha Foods', 'Bravo Tech', 'Charlie Farms'], $companies(''));
        $this->assertSame(['Bravo Tech'], $companies('search=BRAVO'));            // company
        $this->assertSame(['Charlie Farms'], $companies('search=pat%20charlie')); // contact person
        $this->assertSame(['Alpha Foods'], $companies('search=alpha%40'));        // email
        $this->assertSame(['Charlie Farms'], $companies('search=mati'));          // city
        $this->assertSame(['Charlie Farms'], $companies('search=oriental'));      // province
        $this->assertSame(['Bravo Tech'], $companies('status=pending'));
        $this->assertSame([], $companies('search=alpha&status=inactive'));
    }

    public function test_a_partner_with_deployments_cannot_be_deleted(): void
    {
        $partner = IndustryPartner::factory()->create();
        Deployment::create([
            'student_id' => Student::factory()->create()->id,
            'industry_partner_id' => $partner->id,
            'status' => 'completed',
            'start_date' => '2026-06-01',
        ]);

        $this->actingAs($this->admin, 'web')
            ->deleteJson("/api/admin/partners/{$partner->user_id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['partner' => 'Deactivate it instead.']);

        $this->assertModelExists($partner);
    }

    public function test_routes_only_resolve_partners(): void
    {
        $coordinator = User::factory()->role(UserRole::Coordinator)->create();

        $this->actingAs($this->admin, 'web');

        $this->deleteJson("/api/admin/partners/{$coordinator->id}")->assertNotFound();
        $this->deleteJson("/api/admin/partners/{$this->admin->id}")->assertNotFound();
        $this->assertModelExists($coordinator);
        $this->assertModelExists($this->admin);
    }
}
