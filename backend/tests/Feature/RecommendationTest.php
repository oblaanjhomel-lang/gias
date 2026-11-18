<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\RecommendationFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_full_partners_are_excluded_except_where_the_student_is_placed(): void
    {
        $student = $this->student($this->bsit, self::TAGUM, ['web' => 3, 'database' => 2]);

        $full = $this->partner('Alpha Full', self::TAGUM, 1, ['web' => 2]);
        $this->deploy($this->student($this->bsit, self::TAGUM), $full);

        $placedHere = $this->partner('Bravo Placed', self::PANABO, 1, ['web' => 2]);
        $this->deploy($student, $placedHere); // full too, but it's this student's placement

        $open = $this->partner('Charlie Open', self::DAVAO, 3, ['web' => 2, 'database' => 2]);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/api/recommendations/students/{$student->user_id}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'student' => ['id', 'name', 'program', 'city', 'skills', 'placement'],
                'partners' => [['id', 'company_name', 'city', 'distance_km', 'intern_slots', 'open', 'skills_needed', 'skills_matched', 'is_current_placement']],
                'rankings' => ['cos', 'wsm', 'fuzzy'],
                'compare' => [['partner_id', 'cos' => ['score', 'rank'], 'wsm', 'fuzzy', 'average_rank']],
                'agreement' => ['all_agree', 'top' => ['cos', 'wsm', 'fuzzy']],
                'excluded',
                'explanations',
            ]]);

        $data = $response->json('data');
        $ranked = array_column($data['partners'], 'company_name');
        sort($ranked);
        $this->assertSame(['Bravo Placed', 'Charlie Open'], $ranked);
        $this->assertSame(['Alpha Full'], array_column($data['excluded'], 'company_name'));
        $this->assertSame('Bravo Placed', $data['student']['placement']['company_name']);
        $this->assertTrue(collect($data['partners'])->firstWhere('company_name', 'Bravo Placed')['is_current_placement']);

        // Explanations for every ranked partner, for all three algorithms.
        $this->assertCount(2, $data['explanations']);
        $explanation = collect($data['explanations'])->firstWhere('partner_id', $open->user_id);
        $this->assertEqualsCanonicalizing(['partner_id', 'cos', 'wsm', 'fuzzy'], array_keys($explanation));
        $this->assertArrayHasKey('rules_fired', $explanation['fuzzy']);
        $this->assertArrayHasKey('criteria', $explanation['wsm']);
        $this->assertArrayHasKey('dot', $explanation['cos']);
    }

    public function test_the_better_skill_match_ranks_first_and_agreement_is_reported(): void
    {
        $student = $this->student($this->bsit, self::TAGUM, ['web' => 3, 'frontend' => 2, 'database' => 2]);
        $good = $this->partner('Good Fit', self::TAGUM, 4, ['web' => 2, 'frontend' => 2, 'database' => 1]);
        $this->partner('Poor Fit', self::TAGUM, 4, ['gis' => 2, 'writing' => 2]);

        $data = $this->actingAs($this->admin, 'web')
            ->getJson("/api/recommendations/students/{$student->user_id}")
            ->json('data');

        foreach (['cos', 'wsm', 'fuzzy'] as $algorithm) {
            $this->assertSame($good->user_id, $data['rankings'][$algorithm][0]['partner_id'], $algorithm);
            $this->assertSame(1, $data['rankings'][$algorithm][0]['rank']);
        }
        $this->assertTrue($data['agreement']['all_agree']);
        $this->assertSame($good->user_id, $data['compare'][0]['partner_id']);
        $this->assertEquals(1, $data['compare'][0]['average_rank']);
        $this->assertSame(3, collect($data['partners'])->firstWhere('id', $good->user_id)['skills_matched']);
    }

    public function test_coordinators_only_see_their_programs_students(): void
    {
        $mine = $this->student($this->bsit, self::TAGUM, name: 'Ana BSIT');
        $other = $this->student($this->bsab, self::TAGUM, name: 'Ben BSAB');
        $this->partner('Open Co', self::TAGUM, 2);

        $this->actingAs($this->coordinator, 'web');
        $this->getJson("/api/recommendations/students/{$other->user_id}")->assertForbidden();
        $this->postJson("/api/recommendations/students/{$other->user_id}/feedback", ['algorithm' => 'wsm', 'rating' => 4])->assertForbidden();
        $this->getJson("/api/recommendations/students/{$mine->user_id}")->assertOk();

        $this->getJson('/api/recommendations/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ana BSIT')
            ->assertJsonPath('meta', ['placed' => 0, 'total' => 1]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($mine->user, 'web')->getJson('/api/recommendations/students')->assertForbidden();
    }

    public function test_the_draft_never_exceeds_slots_and_saves_nothing(): void
    {
        $p1 = $this->partner('One Slot A', self::TAGUM, 1, ['web' => 2]);
        $p2 = $this->partner('One Slot B', self::PANABO, 1, ['web' => 2]);
        $full = $this->partner('Already Full', self::TAGUM, 1, ['web' => 2]);
        $this->deploy($this->student($this->bsit, self::TAGUM), $full);

        foreach (['A', 'B', 'C'] as $n) {
            $this->student($this->bsit, self::TAGUM, ['web' => 3], name: "Student {$n}");
        }
        $before = Deployment::count();

        $data = $this->actingAs($this->admin, 'web')
            ->postJson('/api/recommendations/draft', ['algorithm' => 'wsm'])
            ->assertOk()
            ->json('data');

        $this->assertSame('wsm', $data['algorithm']);
        $this->assertCount(2, $data['proposals']);
        $this->assertCount(1, $data['unassigned']);
        $perPartner = collect($data['proposals'])->countBy('partner.id');
        $this->assertLessThanOrEqual(1, $perPartner[$p1->user_id] ?? 0);
        $this->assertLessThanOrEqual(1, $perPartner[$p2->user_id] ?? 0);
        $this->assertArrayNotHasKey($full->user_id, $perPartner->all());
        $this->assertCount(2, collect($data['proposals'])->pluck('student.id')->unique());
        $this->assertSame($before, Deployment::count()); // nothing saved

        $this->postJson('/api/recommendations/draft', ['algorithm' => 'magic'])->assertUnprocessable();
    }

    public function test_feedback_is_stored_with_a_snapshot(): void
    {
        $student = $this->student($this->bsit, self::TAGUM, ['web' => 3]);
        $partner = $this->partner('Open Co', self::TAGUM, 2, ['web' => 2]);

        $this->actingAs($this->coordinator, 'web')
            ->postJson("/api/recommendations/students/{$student->user_id}/feedback", [
                'algorithm' => 'fuzzy', 'rating' => 4, 'comment' => 'Sensible top pick.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4);

        $feedback = RecommendationFeedback::sole();
        $this->assertSame($student->id, $feedback->student_id);
        $this->assertSame($this->coordinator->id, $feedback->rated_by);
        $this->assertSame(['fuzzy'], array_keys($feedback->snapshot['rankings']));
        $this->assertSame('Open Co', $feedback->snapshot['rankings']['fuzzy'][0]['company_name']);
        $this->assertSame($partner->user_id, $feedback->snapshot['rankings']['fuzzy'][0]['partner_id']);

        $this->postJson("/api/recommendations/students/{$student->user_id}/feedback", ['algorithm' => 'wsm', 'rating' => 6])
            ->assertUnprocessable()->assertJsonValidationErrors('rating');
    }

    public function test_a_student_sees_their_top_three_without_details(): void
    {
        $student = $this->student($this->bsit, self::TAGUM, ['web' => 3]);
        foreach (['A', 'B', 'C', 'D'] as $n) {
            $this->partner("Open {$n}", self::TAGUM, 2, ['web' => 2]);
        }
        $full = $this->partner('Full Co', self::TAGUM, 1, ['web' => 2]);
        $this->deploy($this->student($this->bsit, self::TAGUM), $full);

        $data = $this->actingAs($student->user, 'web')->getJson('/api/student/recommendations')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->json('data');

        foreach ($data as $row) {
            $this->assertEqualsCanonicalizing(['id', 'company_name', 'city', 'province', 'distance_km', 'open', 'score'], array_keys($row));
            $this->assertNotSame('Full Co', $row['company_name']);
        }
    }

    public function test_a_partner_sees_matching_students_without_contact_data(): void
    {
        $partner = $this->partner('Hiring Co', self::TAGUM, 3, ['web' => 2]);
        $best = $this->student($this->bsit, self::TAGUM, ['web' => 3], name: 'Best Match');
        $this->student($this->bsab, self::TAGUM, ['gis' => 1], name: 'Other Student');
        $placed = $this->student($this->bsit, self::TAGUM, ['web' => 3], name: 'Already Placed');
        $this->deploy($placed, $this->partner('Elsewhere', self::TAGUM, 2));

        $response = $this->actingAs($partner->user, 'web')->getJson('/api/partner/matching-students')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Best Match')
            ->assertJsonPath('data.0.program', 'BSIT')
            ->assertJsonPath('meta', ['open' => 3, 'intern_slots' => 3]);

        $this->assertNotContains('Already Placed', array_column($response->json('data'), 'name'));
        foreach ($response->json('data') as $row) {
            $this->assertSame(['name', 'program', 'score'], array_keys($row));
        }
        $this->assertStringNotContainsString($best->user->email, $response->getContent());
    }
}
