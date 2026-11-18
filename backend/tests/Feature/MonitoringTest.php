<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\WeeklyReportStatus;
use App\Events\DeploymentCompleted;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\IndustryPartner;
use App\Models\Student;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

/**
 * "Today" is 17 Nov 2026 (Asia/Manila) unless a test travels.
 */
class MonitoringTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    private IndustryPartner $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-17 02:00:00');
        $this->setUpFixtures();
        $this->host = $this->partner('Host Co', self::TAGUM, 20);
    }

    private function deployment(Student $student, string $start = '2026-09-01', DeploymentStatus $status = DeploymentStatus::Active): Deployment
    {
        return Deployment::create([
            'student_id' => $student->id, 'industry_partner_id' => $this->host->id, 'status' => $status, 'start_date' => $start,
        ]);
    }

    /** A report on a week, with the period computed by the same rule the API uses. */
    private function report(Deployment $deployment, int $week, float $hours, WeeklyReportStatus $status = WeeklyReportStatus::Signed): WeeklyReport
    {
        [$start, $end] = WeeklyReportService::periodFor($deployment->start_date, $week);

        return WeeklyReport::create([
            'deployment_id' => $deployment->id, 'week_number' => $week, 'period_start' => $start, 'period_end' => $end,
            'daily_hours' => ['mon' => $hours], 'total_hours' => $hours, 'tasks' => 'Routine tasks for the week.',
            'status' => $status,
        ]);
    }

    private function progressOf(Deployment $deployment): array
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->admin, 'web')
            ->getJson("/api/monitoring/deployments/{$deployment->id}")
            ->assertOk()
            ->json('data.progress');
    }

    // Progress

    public function test_progress_counts_signed_hours_only_and_caps_at_100(): void
    {
        $this->bsit->update(['required_ojt_hours' => 100]);
        $d = $this->deployment($this->student($this->bsit, self::TAGUM));
        $this->report($d, 1, 40);
        $this->report($d, 2, 30);
        $this->report($d, 3, 12, WeeklyReportStatus::Submitted);
        $this->report($d, 4, 10, WeeklyReportStatus::Draft);
        $this->report($d, 5, 10, WeeklyReportStatus::Returned);

        $progress = $this->progressOf($d);
        $this->assertEquals(70, $progress['signed_hours']);
        $this->assertEquals(12, $progress['pending_hours']);
        $this->assertEquals(70, $progress['percent']);
        $this->assertSame(100, $progress['required_hours']);
        $this->assertSame(12, $progress['weeks_elapsed']); // 1 Sep → 17 Nov

        $this->report($d, 6, 40);
        $this->assertEquals(100, $this->progressOf($d)['percent']); // 110 / 100, capped
    }

    // Overdue

    public function test_overdue_with_reports_at_the_14_day_boundary(): void
    {
        $d = $this->deployment($this->student($this->bsit, self::TAGUM));
        $this->report($d, 10, 40); // period ends Mon 9 Nov 2026
        $this->report($d, 11, 40, WeeklyReportStatus::Draft); // drafts don't count

        $this->travelTo('2026-11-23 02:00:00'); // exactly 14 days after 9 Nov
        $this->assertFalse($this->progressOf($d)['is_overdue']);
        $this->assertSame([], $this->listIds('overdue'));

        $this->travelTo('2026-11-24 02:00:00'); // 15 days
        $progress = $this->progressOf($d);
        $this->assertTrue($progress['is_overdue']);
        $this->assertSame('overdue', $progress['status']);
        $this->assertSame([$d->id], $this->listIds('overdue'));
    }

    public function test_overdue_without_reports_counts_from_the_start_date(): void
    {
        $d = $this->deployment($this->student($this->bsit, self::TAGUM), '2026-11-03');

        $this->assertFalse($this->progressOf($d)['is_overdue']); // 3 → 17 Nov is exactly 14 days

        $this->travelTo('2026-11-18 02:00:00');
        $this->assertTrue($this->progressOf($d)['is_overdue']);
        $this->assertSame([$d->id], $this->listIds('overdue'));
    }

    /** @return list<int> */
    private function listIds(string $filter, ?string $extra = ''): array
    {
        $this->app['auth']->forgetGuards();

        return array_column($this->actingAs($this->admin, 'web')
            ->getJson("/api/monitoring/deployments?filter={$filter}{$extra}")
            ->assertOk()
            ->json('data'), 'id');
    }

    // Complete

    public function test_completion_needs_the_required_signed_hours(): void
    {
        Event::fake([DeploymentCompleted::class]);
        $d = $this->deployment($this->student($this->bsit, self::TAGUM));
        foreach (range(1, 11) as $week) {
            $this->report($d, $week, 40); // 440 signed
        }

        $this->actingAs($this->coordinator, 'web')
            ->postJson("/api/monitoring/deployments/{$d->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deployment' => '46 more countersigned hours are needed (440 of 486 h signed).']);

        $this->postJson("/api/monitoring/deployments/{$d->id}/complete", ['force' => true, 'note' => 'Coordinators cannot force this.'])
            ->assertForbidden();
        Event::assertNotDispatched(DeploymentCompleted::class);

        $this->report($d, 12, 46); // 486

        // Module 7: hours are enough, but the FINAL evaluation must be reviewed too.
        $this->postJson("/api/monitoring/deployments/{$d->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deployment' => 'The final evaluation hasn’t been reviewed yet.']);
        $this->postJson("/api/monitoring/deployments/{$d->id}/complete", ['force' => true, 'note' => 'Coordinators cannot force this.'])
            ->assertForbidden();

        Evaluation::create([
            'deployment_id' => $d->id, 'type' => 'final', 'ratings' => ['quality' => 4], 'partner_mean' => 4,
            'status' => EvaluationStatus::Submitted, 'coordinator_rating' => 4,
        ]);
        $this->postJson("/api/monitoring/deployments/{$d->id}/complete")->assertUnprocessable(); // submitted isn't enough
        Evaluation::query()->update(['status' => EvaluationStatus::Reviewed, 'final_rating' => 4]);

        $this->postJson("/api/monitoring/deployments/{$d->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress.status', 'completed');

        $d->refresh();
        $this->assertSame(DeploymentStatus::Completed, $d->status);
        $this->assertSame($this->coordinator->id, $d->completed_by);
        $this->assertNotNull($d->completed_at);
        Event::assertDispatched(DeploymentCompleted::class, fn ($e) => $e->deployment->id === $d->id);

        $this->postJson("/api/monitoring/deployments/{$d->id}/complete")->assertUnprocessable(); // already completed
    }

    public function test_an_admin_can_force_completion_without_a_reviewed_final(): void
    {
        $d = $this->deployment($this->student($this->bsit, self::TAGUM));
        foreach (range(1, 12) as $week) {
            $this->report($d, $week, 41); // 492 ≥ 486, but no final evaluation
        }

        $this->actingAs($this->admin, 'web');
        $this->postJson("/api/monitoring/deployments/{$d->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deployment' => 'final evaluation']);
        $this->postJson("/api/monitoring/deployments/{$d->id}/complete", [
            'force' => true, 'note' => 'Partner closed before evaluating; coordinator verified the work.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_an_admin_can_force_completion_with_a_reason(): void
    {
        $d = $this->deployment($this->student($this->bsit, self::TAGUM));
        $this->report($d, 1, 40);

        $this->actingAs($this->admin, 'web');
        $this->postJson("/api/monitoring/deployments/{$d->id}/complete", ['force' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/monitoring/deployments/{$d->id}/complete", ['force' => true, 'note' => 'Short'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->postJson("/api/monitoring/deployments/{$d->id}/complete", [
            'force' => true, 'note' => 'Hours credited from the summer bridging program.',
        ])->assertOk()->assertJsonPath('data.completion_note', 'Hours credited from the summer bridging program.');
    }

    // Scope, list, summary

    public function test_coordinators_only_see_their_programs_deployments(): void
    {
        $other = $this->deployment($this->student($this->bsab, self::TAGUM));
        $mine = $this->deployment($this->student($this->bsit, self::TAGUM));

        $this->actingAs($this->coordinator, 'web');
        $this->getJson("/api/monitoring/deployments/{$other->id}")->assertForbidden();
        $this->postJson("/api/monitoring/deployments/{$other->id}/complete")->assertForbidden();
        $this->getJson("/api/monitoring/deployments/{$mine->id}")->assertOk();
        $this->getJson("/api/monitoring/deployments?program_id={$this->bsab->id}") // admin-only filter: ignored
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('meta.scope_total', 1);
    }

    public function test_list_filters_search_and_summary(): void
    {
        $active = $this->deployment($this->student($this->bsit, self::TAGUM, name: 'Ana Active'));
        $this->report($active, 11, 40); // ended 16 Nov: on track
        $this->report($active, 12, 8, WeeklyReportStatus::Submitted);
        $overdue = $this->deployment($this->student($this->bsit, self::TAGUM, name: 'Ben Overdue'));
        $this->report($overdue, 2, 40);
        $completed = $this->deployment($this->student($this->bsab, self::TAGUM, name: 'Cara Done'), status: DeploymentStatus::Completed);
        $withdrawn = $this->deployment($this->student($this->bsab, self::TAGUM, name: 'Dan Left'), status: DeploymentStatus::Withdrawn);
        $active->student->update(['student_number' => '2023-77777']);

        $this->assertEqualsCanonicalizing([$active->id, $overdue->id, $completed->id, $withdrawn->id], $this->listIds('all'));
        $this->assertEqualsCanonicalizing([$active->id, $overdue->id], $this->listIds('active'));
        $this->assertSame([$overdue->id], $this->listIds('overdue'));
        $this->assertSame([$completed->id], $this->listIds('completed'));
        $this->assertSame([$withdrawn->id], $this->listIds('withdrawn'));
        $this->assertSame([$active->id], $this->listIds('all', '&search=77777'));   // student number
        $this->assertSame([$overdue->id], $this->listIds('all', '&search=ben'));    // name
        $this->assertCount(4, $this->listIds('all', '&search=host'));             // company
        $this->assertSame([$completed->id, $withdrawn->id], $this->listIds('all', "&program_id={$this->bsab->id}"));

        $this->actingAs($this->admin, 'web')->getJson('/api/monitoring/summary')
            ->assertOk()
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.overdue', 1)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.withdrawn', 1)
            ->assertJsonPath('data.reports_awaiting_signature', 1)
            ->assertJsonPath('data.average_percent', round((40 / 486 * 100 + 40 / 486 * 100) / 2, 1));

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')->getJson('/api/monitoring/summary')
            ->assertJsonPath('data.completed', 0)
            ->assertJsonPath('data.active', 2);
    }

    public function test_the_list_has_no_n_plus_one(): void
    {
        $count = function (): int {
            $this->app['auth']->forgetGuards();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin, 'web')->getJson('/api/monitoring/deployments')->assertOk();

            return count(DB::getQueryLog());
        };

        $first = $this->deployment($this->student($this->bsit, self::TAGUM));
        $this->report($first, 1, 40);
        $one = $count();

        foreach (range(1, 9) as $i) {
            $d = $this->deployment($this->student($i % 2 ? $this->bsab : $this->bsit, self::TAGUM));
            $this->report($d, 1, 40);
        }
        $ten = $count();

        $this->assertSame($one, $ten, "Queries grew from {$one} (1 row) to {$ten} (10 rows)");
    }

    // Partner interns

    public function test_partners_see_their_active_and_completed_interns(): void
    {
        $active = $this->deployment($this->student($this->bsit, self::TAGUM, name: 'Ana Active'));
        $this->report($active, 1, 40);
        $this->deployment($this->student($this->bsit, self::TAGUM, name: 'Cara Done'), status: DeploymentStatus::Completed);
        $this->deployment($this->student($this->bsit, self::TAGUM, name: 'Dan Left'), status: DeploymentStatus::Withdrawn);
        $elsewhere = $this->partner('Elsewhere', self::PANABO, 2);
        $this->deploy($this->student($this->bsit, self::TAGUM, name: 'Not Mine'), $elsewhere);

        $this->actingAs($this->host->user, 'web')->getJson('/api/partner/interns')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.intern', ['name' => 'Ana Active', 'program' => 'BSIT'])
            ->assertJsonPath('data.0.progress.signed_hours', 40)
            ->assertJsonPath('data.1.status', 'completed');
    }

    // Required hours

    public function test_admin_sets_required_hours_per_program(): void
    {
        $this->actingAs($this->admin, 'web');
        $this->putJson("/api/admin/programs/{$this->bsit->id}/required-hours", ['required_ojt_hours' => 99])
            ->assertUnprocessable()->assertJsonValidationErrors('required_ojt_hours');
        $this->putJson("/api/admin/programs/{$this->bsit->id}/required-hours", ['required_ojt_hours' => 2001])
            ->assertUnprocessable();
        $this->putJson("/api/admin/programs/{$this->bsit->id}/required-hours", ['required_ojt_hours' => 600])
            ->assertOk()
            ->assertJsonPath('data.required_ojt_hours', 600);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')
            ->putJson("/api/admin/programs/{$this->bsit->id}/required-hours", ['required_ojt_hours' => 300])
            ->assertForbidden();
        $this->assertSame(600, $this->bsit->fresh()->required_ojt_hours);
    }
}
