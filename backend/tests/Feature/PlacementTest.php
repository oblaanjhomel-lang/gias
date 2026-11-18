<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

class PlacementTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_placing_creates_an_active_deployment_with_ranks_and_a_snapshot(): void
    {
        $student = $this->student($this->bsit, self::TAGUM, ['web' => 3, 'database' => 2]);
        $best = $this->partner('Best Fit', self::TAGUM, 3, ['web' => 2, 'database' => 2]);
        $second = $this->partner('Second Fit', self::PANABO, 3, ['gis' => 2]);

        $this->actingAs($this->coordinator, 'web')
            ->postJson('/api/placements', [
                'student_id' => $student->user_id,
                'partner_id' => $second->user_id,
                'algorithm' => 'wsm',
                'start_date' => '2026-09-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.partner.company_name', 'Second Fit')
            ->assertJsonPath('data.algorithm_used', 'wsm');

        $deployment = Deployment::sole();
        $this->assertSame(DeploymentStatus::Active, $deployment->status);
        $this->assertSame($this->coordinator->id, $deployment->assigned_by);
        $this->assertSame([2, 2, 2], [$deployment->rank_cos, $deployment->rank_wsm, $deployment->rank_fuzzy]);
        $this->assertSame('wsm', $deployment->algorithm_used);
        $this->assertSame(
            ['Best Fit', 'Second Fit'],
            array_column($deployment->recommendation_snapshot['rankings']['wsm'], 'company_name'),
        );
        $this->assertSame($best->user_id, $deployment->recommendation_snapshot['rankings']['cos'][0]['partner_id']);
    }

    public function test_a_placed_student_cannot_be_placed_again(): void
    {
        $student = $this->student($this->bsit, self::TAGUM);
        $a = $this->partner('A', self::TAGUM, 2);
        $b = $this->partner('B', self::TAGUM, 2);
        $this->actingAs($this->admin, 'web');

        $this->postJson('/api/placements', ['student_id' => $student->user_id, 'partner_id' => $a->user_id, 'start_date' => '2026-09-01'])
            ->assertCreated()
            ->assertJsonPath('data.algorithm_used', 'manual');

        $this->postJson('/api/placements', ['student_id' => $student->user_id, 'partner_id' => $b->user_id, 'start_date' => '2026-09-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id' => 'already placed']);

        $this->assertSame(1, Deployment::count());
    }

    public function test_a_full_partner_is_rejected(): void
    {
        $full = $this->partner('Full Co', self::TAGUM, 1);
        $this->deploy($this->student($this->bsit, self::TAGUM), $full);
        $student = $this->student($this->bsit, self::TAGUM);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/placements', ['student_id' => $student->user_id, 'partner_id' => $full->user_id, 'start_date' => '2026-09-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_id' => 'no open slots']);
    }

    public function test_coordinators_cannot_place_other_programs_students(): void
    {
        $student = $this->student($this->bsab, self::TAGUM);
        $partner = $this->partner('Open Co', self::TAGUM, 2);

        $this->actingAs($this->coordinator, 'web')
            ->postJson('/api/placements', ['student_id' => $student->user_id, 'partner_id' => $partner->user_id, 'start_date' => '2026-09-01'])
            ->assertForbidden();

        $this->assertSame(0, Deployment::count());
    }

    public function test_the_partner_row_is_locked_while_placing(): void
    {
        $student = $this->student($this->bsit, self::TAGUM);
        $partner = $this->partner('Last Slot Co', self::TAGUM, 1);

        DB::enableQueryLog();
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/placements', ['student_id' => $student->user_id, 'partner_id' => $partner->user_id, 'start_date' => '2026-09-01'])
            ->assertCreated();

        $locks = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql) => str_contains($sql, '"industry_partners"') && str_ends_with(trim($sql), 'for update'));
        $this->assertCount(1, $locks, 'Expected SELECT … FROM industry_partners … FOR UPDATE');

        // The capacity check runs under that lock: the slot is gone for the next student.
        $this->postJson('/api/placements', [
            'student_id' => $this->student($this->bsit, self::TAGUM)->user_id,
            'partner_id' => $partner->user_id,
            'start_date' => '2026-09-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('partner_id');
    }

    public function test_a_batch_is_all_or_nothing(): void
    {
        $ok = $this->partner('Open Co', self::TAGUM, 2);
        $full = $this->partner('Full Co', self::TAGUM, 1);
        $this->deploy($this->student($this->bsit, self::TAGUM), $full);
        $first = $this->student($this->bsit, self::TAGUM, name: 'First Student');
        $second = $this->student($this->bsit, self::TAGUM, name: 'Second Student');
        $before = Deployment::count();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/placements/batch', ['items' => [
                ['student_id' => $first->user_id, 'partner_id' => $ok->user_id, 'algorithm' => 'wsm', 'start_date' => '2026-09-01'],
                ['student_id' => $second->user_id, 'partner_id' => $full->user_id, 'algorithm' => 'wsm', 'start_date' => '2026-09-01'],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1' => 'Item 2 (Second Student → Full Co): Full Co has no open slots.']);

        $this->assertSame($before, Deployment::count()); // the first placement was rolled back too

        $this->postJson('/api/placements/batch', ['items' => [
            ['student_id' => $first->user_id, 'partner_id' => $ok->user_id, 'algorithm' => 'cos', 'start_date' => '2026-09-01'],
            ['student_id' => $second->user_id, 'partner_id' => $ok->user_id, 'algorithm' => 'cos', 'start_date' => '2026-09-01'],
        ]])->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame($before + 2, Deployment::count());
    }

    public function test_withdraw_frees_the_slot(): void
    {
        $partner = $this->partner('One Slot Co', self::TAGUM, 1);
        $deployment = $this->deploy($this->student($this->bsit, self::TAGUM), $partner);
        $next = $this->student($this->bsit, self::TAGUM);

        $this->actingAs($this->coordinator, 'web')
            ->patchJson("/api/placements/{$deployment->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn')
            ->assertJsonPath('data.end_date', now()->toDateString());

        $this->patchJson("/api/placements/{$deployment->id}/withdraw")->assertUnprocessable();

        $this->postJson('/api/placements', ['student_id' => $next->user_id, 'partner_id' => $partner->user_id, 'start_date' => '2026-09-01'])
            ->assertCreated();
    }

    public function test_coordinators_cannot_withdraw_other_programs_placements(): void
    {
        $deployment = $this->deploy($this->student($this->bsab, self::TAGUM), $this->partner('Co', self::TAGUM, 2));

        $this->actingAs($this->coordinator, 'web')
            ->patchJson("/api/placements/{$deployment->id}/withdraw")
            ->assertForbidden();
    }
}
