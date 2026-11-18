<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\GeocodeStatus;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Deployment;
use App\Models\IndustryPartner;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixture (see setUp):
 *   Alpha   Tagum   (DDN)              active   5 slots, 0 filled (+1 completed) → open
 *   Bravo   Panabo  (DDN)              active   3 slots, 1 filled (BSIT)         → near
 *   Charlie Mati    (Davao Oriental)   active   2 slots, 2 filled (BSIT, BSAB)   → full
 *   Delta   Davao City                 PENDING  4 slots                          → pending
 *   Echo    (no location, DDN)         active   4 slots                          → unlocated
 *   Foxtrot Tagum                      INACTIVE → never shown
 * Unplaced active students: near Tagum (BSIT), in Mati (BSAB), no home (BSIT); plus one pending student.
 */
class MapTest extends TestCase
{
    use RefreshDatabase;

    private const TAGUM = [7.4478, 125.8078];

    private const PANABO = [7.3081, 125.6841];

    private const MATI = [6.9550, 126.2167];

    private const DAVAO = [7.0731, 125.6128];

    private User $admin;

    private User $coordinator;

    private Program $bsit;

    private Program $bsab;

    private IndustryPartner $alpha;

    private IndustryPartner $bravo;

    private IndustryPartner $charlie;

    private IndustryPartner $delta;

    private IndustryPartner $echo;

    private Student $nearTagum;

    private Student $noHome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $this->bsab = Program::create(['code' => 'BSAB', 'name' => 'BS Agribusiness']);
        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->coordinator = User::factory()->role(UserRole::Coordinator)->create(['program_id' => $this->bsit->id]);

        $this->alpha = $this->partner('Alpha Foods', self::TAGUM, 5);
        $this->bravo = $this->partner('Bravo Tech', self::PANABO, 3);
        $this->charlie = $this->partner('Charlie Farms', self::MATI, 2, province: Province::DavaoOriental);
        $this->delta = $this->partner('Delta Pending Corp.', self::DAVAO, 4, UserStatus::Pending, Province::DavaoCity);
        $this->echo = $this->partner('Echo Unlocated', null, 4);
        $this->partner('Foxtrot Inactive', self::TAGUM, 4, UserStatus::Inactive);

        $this->deploy($this->student($this->bsit, [7.31, 125.69]), $this->bravo);
        $this->deploy($this->student($this->bsit, [6.96, 126.21]), $this->charlie);
        $this->deploy($this->student($this->bsab, [6.96, 126.21]), $this->charlie);
        $this->deploy($this->student($this->bsit, self::TAGUM), $this->alpha, DeploymentStatus::Completed);

        $this->nearTagum = $this->student($this->bsit, [7.45, 125.81]);
        $this->student($this->bsab, [6.96, 126.21]);
        $this->noHome = $this->student($this->bsit, null);
        $this->student($this->bsit, [7.45, 125.81], UserStatus::Pending);
    }

    // Fixture helpers

    private function partner(
        string $company, ?array $at, int $slots, UserStatus $status = UserStatus::Active, Province $province = Province::DavaoDelNorte,
    ): IndustryPartner {
        return IndustryPartner::factory()
            ->for(User::factory()->role(UserRole::Partner)->status($status))
            ->create([
                'company_name' => $company,
                'intern_slots' => $slots,
                'province' => $province,
                'location' => $at ? Point::makeGeodetic($at[0], $at[1]) : null,
                'geocode_status' => $at ? GeocodeStatus::Located : GeocodeStatus::Failed,
            ]);
    }

    private function student(Program $program, ?array $home, UserStatus $status = UserStatus::Active): Student
    {
        return Student::factory()
            ->for(User::factory()->role(UserRole::Student)->status($status)->state(['program_id' => $program->id]))
            ->create(['home_location' => $home ? Point::makeGeodetic($home[0], $home[1]) : null]);
    }

    private function deploy(Student $student, IndustryPartner $partner, DeploymentStatus $status = DeploymentStatus::Active): Deployment
    {
        return Deployment::create([
            'student_id' => $student->id,
            'industry_partner_id' => $partner->id,
            'status' => $status,
            'start_date' => '2026-09-01',
        ]);
    }

    /** @return array<string, array<string, mixed>> company => properties */
    private function features(User $viewer, string $query = ''): array
    {
        $this->app['auth']->forgetGuards();

        return collect($this->actingAs($viewer, 'web')->getJson('/api/map/partners'.$query)->assertOk()->json('features'))
            ->mapWithKeys(fn (array $f) => [$f['properties']['company_name'] => $f['properties']])
            ->all();
    }

    // GeoJSON

    public function test_admin_gets_a_feature_collection_with_saturation_buckets(): void
    {
        $response = $this->actingAs($this->admin, 'web')->getJson('/api/map/partners')->assertOk();

        $response->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('features.0.type', 'Feature')
            ->assertJsonPath('features.0.id', $this->alpha->user_id)
            ->assertJsonPath('features.0.geometry', ['type' => 'Point', 'coordinates' => [125.8078, 7.4478]])
            ->assertJsonStructure(['features' => [['properties' => [
                'id', 'company_name', 'city', 'province', 'status', 'intern_slots', 'filled', 'open',
                'saturation', 'interns_by_program', 'geocode_status', 'contact_person', 'contact_number', 'email',
            ]]]]);

        $saturation = collect($response->json('features'))->pluck('properties.saturation', 'properties.company_name')->all();

        // Unlocated (Echo) and inactive (Foxtrot) partners are not on the map.
        $this->assertSame([
            'Alpha Foods' => 'open',
            'Bravo Tech' => 'near',
            'Charlie Farms' => 'full',
            'Delta Pending Corp.' => 'pending',
        ], $saturation);
    }

    public function test_filled_and_open_count_only_active_deployments(): void
    {
        $features = $this->features($this->admin);

        $this->assertSame([0, 5], [$features['Alpha Foods']['filled'], $features['Alpha Foods']['open']]); // completed ignored
        $this->assertSame([1, 2], [$features['Bravo Tech']['filled'], $features['Bravo Tech']['open']]);
        $this->assertSame([2, 0], [$features['Charlie Farms']['filled'], $features['Charlie Farms']['open']]);
        $this->assertSame(
            [['code' => 'BSAB', 'count' => 1], ['code' => 'BSIT', 'count' => 1]],
            $features['Charlie Farms']['interns_by_program'],
        );
    }

    public function test_filters(): void
    {
        $this->assertSame(['Charlie Farms'], array_keys($this->features($this->admin, '?province=Davao%20Oriental')));
        $this->assertSame(['Charlie Farms'], array_keys($this->features($this->admin, "?program_id={$this->bsab->id}")));
        $this->assertSame(['Bravo Tech', 'Charlie Farms'], array_keys($this->features($this->admin, "?program_id={$this->bsit->id}")));
        $this->assertSame(
            ['Bravo Tech', 'Delta Pending Corp.'],
            array_keys($this->features($this->admin, '?saturation[]=near&saturation[]=pending')),
        );

        $this->actingAs($this->admin, 'web')->getJson('/api/map/partners?saturation[]=bogus')->assertUnprocessable();
        $this->getJson('/api/map/partners?province=Cebu')->assertUnprocessable();
    }

    // Visibility per role

    public function test_coordinators_see_active_partners_with_contacts(): void
    {
        $features = $this->features($this->coordinator);

        $this->assertSame(['Alpha Foods', 'Bravo Tech', 'Charlie Farms'], array_keys($features));
        $this->assertArrayHasKey('contact_person', $features['Alpha Foods']);
        $this->assertArrayHasKey('interns_by_program', $features['Alpha Foods']);
    }

    public function test_students_see_only_partners_with_open_slots_and_no_contacts(): void
    {
        $features = $this->features($this->nearTagum->user);

        $this->assertSame(['Alpha Foods', 'Bravo Tech'], array_keys($features)); // no full, no pending
        foreach ($features as $properties) {
            foreach (['contact_person', 'contact_number', 'email', 'address', 'interns_by_program'] as $hidden) {
                $this->assertArrayNotHasKey($hidden, $properties);
            }
        }
    }

    public function test_a_partner_sees_only_their_own_feature(): void
    {
        $features = $this->features($this->bravo->user);
        $this->assertSame(['Bravo Tech'], array_keys($features));
        $this->assertSame($this->bravo->address, $features['Bravo Tech']['address']);

        // Not located yet: still their own feature, with a null geometry.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->echo->user, 'web')->getJson('/api/map/partners')
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.geometry', null)
            ->assertJsonPath('features.0.properties.geocode_status', 'failed');
    }

    // Location updates

    public function test_admin_can_move_any_partner_which_sets_a_manual_pin(): void
    {
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/map/partners/{$this->echo->user_id}/location", ['latitude' => 7.18, 'longitude' => 125.95])
            ->assertOk()
            ->assertJsonPath('geometry.coordinates', [125.95, 7.18])
            ->assertJsonPath('properties.geocode_status', 'manual');

        $this->echo->refresh();
        $this->assertSame(GeocodeStatus::Manual, $this->echo->geocode_status);
        $this->assertEqualsWithDelta(7.18, $this->echo->location->getLatitude(), 0.000001);
    }

    public function test_a_partner_can_move_only_their_own_pin(): void
    {
        $this->actingAs($this->bravo->user, 'web')
            ->patchJson("/api/map/partners/{$this->bravo->user_id}/location", ['latitude' => 7.31, 'longitude' => 125.69])
            ->assertOk()
            ->assertJsonPath('properties.geocode_status', 'manual');

        $this->patchJson("/api/map/partners/{$this->alpha->user_id}/location", ['latitude' => 7.31, 'longitude' => 125.69])
            ->assertForbidden();
        $this->assertEqualsWithDelta(7.4478, $this->alpha->fresh()->location->getLatitude(), 0.000001);
    }

    public function test_other_roles_cannot_move_pins_and_coordinates_must_be_in_the_region(): void
    {
        foreach ([$this->coordinator, $this->nearTagum->user] as $user) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($user, 'web')
                ->patchJson("/api/map/partners/{$this->alpha->user_id}/location", ['latitude' => 7.3, 'longitude' => 125.7])
                ->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/map/partners/{$this->alpha->user_id}/location", ['latitude' => 10.3157, 'longitude' => 123.8854])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->patchJson("/api/map/partners/{$this->coordinator->id}/location", ['latitude' => 7.3, 'longitude' => 125.7])
            ->assertNotFound(); // not a partner
    }

    public function test_unlocated_partners_for_admin_only(): void
    {
        $this->actingAs($this->admin, 'web')->getJson('/api/map/unlocated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->echo->user_id)
            ->assertJsonPath('data.0.geocode_status', 'failed');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')->getJson('/api/map/unlocated')->assertForbidden();
    }

    // Nearby

    public function test_nearby_partners_are_ordered_by_distance(): void
    {
        $panaboStudent = $this->student($this->bsit, [7.31, 125.69]);

        $response = $this->actingAs($panaboStudent->user, 'web')->getJson('/api/map/nearby')
            ->assertOk()
            ->assertJsonPath('meta.home_missing', false)
            ->assertJsonPath('meta.home.latitude', 7.31);

        // Bravo (Panabo, ~0.6 km) before Alpha (Tagum, ~21 km); full / pending / unlocated excluded.
        $this->assertSame(['Bravo Tech', 'Alpha Foods'], array_column($response->json('data'), 'company_name'));
        $this->assertLessThan(1, $response->json('data.0.distance_km'));
        $this->assertEqualsWithDelta(21, $response->json('data.1.distance_km'), 2);
        $this->assertSame(2, $response->json('data.0.open'));
    }

    public function test_nearby_without_a_home_location(): void
    {
        $this->actingAs($this->noHome->user, 'web')->getJson('/api/map/nearby')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.home_missing', true);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'web')->getJson('/api/map/nearby')->assertForbidden();
    }

    // Summary

    public function test_summary_totals_provinces_and_coverage_for_admin(): void
    {
        $response = $this->actingAs($this->admin, 'web')->getJson('/api/map/summary')->assertOk();

        $response->assertJsonPath('partners', 4) // active: Alpha, Bravo, Charlie, Echo
            ->assertJsonPath('interns_deployed', 3)
            ->assertJsonPath('open_slots', 11) // 5 + 2 + 0 + 4
            ->assertJsonPath('coverage', [
                'radius_km' => 25,
                // near Tagum, in Mati, no home, and the Tagum student whose deployment is only
                // *completed* (no active one). The pending student is excluded.
                'students_unplaced' => 4,
                'within_radius' => 2, // the two Tagum homes (Alpha); Mati's partner is full
                'percent' => 50,
            ]);

        $provinces = collect($response->json('provinces'))->keyBy('province');
        $this->assertCount(6, $provinces);
        $this->assertSame(['province' => 'Davao del Norte', 'partners' => 3, 'interns' => 1, 'open_slots' => 11], $provinces['Davao del Norte']);
        $this->assertSame(['province' => 'Davao Oriental', 'partners' => 1, 'interns' => 2, 'open_slots' => 0], $provinces['Davao Oriental']);
        $this->assertSame(['province' => 'Davao Occidental', 'partners' => 0, 'interns' => 0, 'open_slots' => 0], $provinces['Davao Occidental']);
    }

    public function test_summary_is_scoped_to_the_coordinators_program(): void
    {
        $this->actingAs($this->coordinator, 'web')->getJson('/api/map/summary?radius_km=50')
            ->assertOk()
            ->assertJsonPath('interns_deployed', 2) // BSIT interns at Bravo and Charlie
            ->assertJsonPath('coverage', [
                'radius_km' => 50,
                'students_unplaced' => 3, // BSIT only: near Tagum, completed-only, no home
                'within_radius' => 2,
                'percent' => 66.7,
            ]);

        $this->getJson('/api/map/summary?radius_km=500')->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->nearTagum->user, 'web')->getJson('/api/map/summary')->assertForbidden();
    }

    // Config

    public function test_config_for_any_signed_in_user(): void
    {
        $this->actingAs($this->nearTagum->user, 'web')->getJson('/api/map/config')
            ->assertOk()
            ->assertJsonPath('campus.name', 'DNSC Main Campus')
            ->assertJsonPath('region_bounds.min_lat', 5.5)
            ->assertJsonPath('coverage_radius_km', 25);
    }

    // Data integrity

    public function test_a_student_can_have_only_one_active_deployment(): void
    {
        $student = $this->student($this->bsit, self::TAGUM);
        $this->deploy($student, $this->alpha);
        $this->deploy($student, $this->bravo, DeploymentStatus::Completed); // history is fine

        $this->expectException(UniqueConstraintViolationException::class);
        $this->deploy($student, $this->bravo);
    }
}
