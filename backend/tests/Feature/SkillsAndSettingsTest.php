<?php

namespace Tests\Feature;

use App\Models\RecommendationFeedback;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

class SkillsAndSettingsTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    // Skills list (admin)

    public function test_skills_list_per_role(): void
    {
        $this->skill('gis')->update(['is_active' => false]);
        $student = $this->student($this->bsit, self::TAGUM);

        $this->actingAs($student->user, 'web')->getJson('/api/skills')
            ->assertOk()
            ->assertJsonCount(11, 'data')
            ->assertJsonMissingPath('data.0.partners_count');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'web')->getJson('/api/skills')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'is_active', 'partners_count', 'students_count']]]);
    }

    public function test_admin_manages_skills_and_can_only_delete_unused_ones(): void
    {
        $this->actingAs($this->admin, 'web');

        $id = $this->postJson('/api/admin/skills', ['name' => 'Drone mapping'])->assertCreated()->json('data.id');
        $this->postJson('/api/admin/skills', ['name' => 'Drone mapping'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/admin/skills/{$id}", ['name' => 'Drone surveying'])->assertOk()->assertJsonPath('data.name', 'Drone surveying');
        $this->putJson("/api/admin/skills/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/admin/skills/{$id}")->assertNoContent();

        $used = $this->skill('web');
        $this->student($this->bsit, self::TAGUM, ['web' => 2]);
        $this->deleteJson("/api/admin/skills/{$used->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skill' => 'Deactivate it instead (is_active = false).']);
        $this->assertModelExists($used);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')->postJson('/api/admin/skills', ['name' => 'Nope'])->assertForbidden();
    }

    // Own skills

    public function test_partners_manage_only_their_own_needed_skills(): void
    {
        $partner = $this->partner('Hiring Co', self::TAGUM, 3);
        $web = $this->skill('web');
        $data = $this->skill('data');

        $this->actingAs($partner->user, 'web')
            ->putJson('/api/partner/skills', ['skills' => [
                ['skill_id' => $web->id, 'importance' => 2],
                ['skill_id' => $data->id, 'importance' => 1],
            ]])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/partner/skills')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Data analysis')
            ->assertJsonPath('data.0.importance', 1)
            ->assertJsonPath('data.1.importance', 2);

        $this->putJson('/api/partner/skills', ['skills' => [['skill_id' => $web->id, 'importance' => 3]]])
            ->assertUnprocessable()->assertJsonValidationErrors('skills.0.importance');
        $this->putJson('/api/partner/skills', ['skills' => [['skill_id' => 9999, 'importance' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('skills.0.skill_id');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->student($this->bsit, self::TAGUM)->user, 'web')
            ->putJson('/api/partner/skills', ['skills' => []])->assertForbidden();
    }

    public function test_students_manage_their_own_skills_and_inactive_links_survive_a_save(): void
    {
        $student = $this->student($this->bsit, self::TAGUM, ['gis' => 2]);
        $this->skill('gis')->update(['is_active' => false]);
        $web = $this->skill('web');

        $this->actingAs($student->user, 'web')
            ->putJson('/api/student/skills', ['skills' => [['skill_id' => $web->id, 'level' => 3]]])
            ->assertOk()
            ->assertJsonPath('data', [['skill_id' => $web->id, 'name' => $web->name, 'level' => 3]]);

        // The deactivated GIS link is kept (hidden), so re-activating the skill brings it back.
        $this->assertSame(2, $student->skills()->count());

        $this->putJson('/api/student/skills', ['skills' => [['skill_id' => $web->id, 'level' => 4]]])
            ->assertUnprocessable()->assertJsonValidationErrors('skills.0.level');
        $this->putJson('/api/student/skills', ['skills' => [
            ['skill_id' => $web->id, 'level' => 1], ['skill_id' => $web->id, 'level' => 2],
        ]])->assertUnprocessable()->assertJsonValidationErrors('skills.0.skill_id');
        $this->putJson('/api/student/skills', ['skills' => [['skill_id' => $this->skill('gis')->id, 'level' => 1]]])
            ->assertUnprocessable(); // inactive skill can't be added
    }

    // Settings

    public function test_recommendation_settings(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->getJson('/api/admin/recommendation-settings')
            ->assertOk()
            ->assertJsonPath('data.weight_coverage', 40)
            ->assertJsonPath('data.slot_saturation', 5)
            ->assertJsonPath('data.fuzzy.rules.0', ['fit' => 'high', 'distance' => 'near', 'then' => 'excellent']);

        $this->putJson('/api/admin/recommendation-settings', [
            'weight_coverage' => 10, 'weight_proficiency' => 10, 'weight_proximity' => 0, 'weight_slots' => 0,
            'proximity_zero_km' => 50, 'slot_saturation' => 2,
        ])->assertOk()
            ->assertJsonPath('data.normalised_weights.coverage', 0.5)
            ->assertJsonPath('data.updated_by', $this->admin->name);

        $this->putJson('/api/admin/recommendation-settings', [
            'weight_coverage' => 0, 'weight_proficiency' => 0, 'weight_proximity' => 0, 'weight_slots' => 0,
            'proximity_zero_km' => 50, 'slot_saturation' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('weights');

        $this->putJson('/api/admin/recommendation-settings', [
            'weight_coverage' => 101, 'weight_proficiency' => 0, 'weight_proximity' => 0, 'weight_slots' => 0,
            'proximity_zero_km' => 5, 'slot_saturation' => 51,
        ])->assertUnprocessable()->assertJsonValidationErrors(['weight_coverage', 'proximity_zero_km', 'slot_saturation']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')->getJson('/api/admin/recommendation-settings')->assertForbidden();
    }

    // Export

    public function test_csv_exports(): void
    {
        $student = $this->student($this->bsit, self::TAGUM, ['web' => 3], name: 'Juan Dela Cruz');
        $partner = $this->partner('=HYPERLINK("http://x")', self::TAGUM, 2, ['web' => 2]);
        $this->actingAs($this->admin, 'web')->postJson('/api/placements', [
            'student_id' => $student->user_id, 'partner_id' => $partner->user_id, 'algorithm' => 'fuzzy', 'start_date' => '2026-09-01',
        ])->assertCreated();
        RecommendationFeedback::create([
            'student_id' => $student->id, 'algorithm' => 'wsm', 'rating' => 5, 'comment' => 'Spot on',
            'rated_by' => $this->admin->id, 'snapshot' => [],
        ]);

        $placements = $this->get('/api/admin/recommendations/export?type=placements');
        $placements->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_map('str_getcsv', explode("\n", trim(ltrim($placements->streamedContent(), "\xEF\xBB\xBF"))));
        $this->assertSame(['student', 'program', 'partner', 'algorithm_used', 'rank_cos', 'rank_wsm', 'rank_fuzzy', 'date', 'status'], $lines[0]);
        $this->assertSame(['Juan Dela Cruz', 'BSIT', '\'=HYPERLINK("http://x")', 'fuzzy', '1', '1', '1', '2026-09-01', 'active'], $lines[1]);

        $feedback = $this->get('/api/admin/recommendations/export?type=feedback');
        $lines = array_map('str_getcsv', explode("\n", trim(ltrim($feedback->streamedContent(), "\xEF\xBB\xBF"))));
        $this->assertSame(['student', 'program', 'algorithm', 'rating', 'comment', 'rated_by', 'date'], $lines[0]);
        $this->assertSame(['Juan Dela Cruz', 'BSIT', 'wsm', '5', 'Spot on', $this->admin->name, now()->toDateString()], $lines[1]);

        $this->getJson('/api/admin/recommendations/export?type=other')->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')->get('/api/admin/recommendations/export?type=placements')->assertForbidden();
    }
}
