<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAnalyticsFixtures;
use Tests\TestCase;

/**
 * Uses the analytics dataset (see BuildsAnalyticsFixtures).
 */
class DashboardTest extends TestCase
{
    use BuildsAnalyticsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAnalytics();
    }

    /** @return array<string, mixed> */
    private function dashboard(User $user): array
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user)->getJson('/api/dashboard')->assertOk()->json('data');
    }

    private function report(string $who, int $week, string $status): void
    {
        $deployment = $this->d[$who];
        [$start, $end] = WeeklyReportService::periodFor($deployment->start_date, $week);
        WeeklyReport::create([
            'deployment_id' => $deployment->id, 'week_number' => $week, 'period_start' => $start, 'period_end' => $end,
            'daily_hours' => ['mon' => 8], 'total_hours' => 8, 'tasks' => 'Routine tasks for the week.', 'status' => $status,
        ]);
    }

    public function test_admin_dashboard(): void
    {
        $this->partner('Pending Co', null, 3, status: UserStatus::Pending);

        $data = $this->dashboard($this->admin);

        $this->assertSame('admin', $data['role']);
        $this->assertSame([
            'active_partners' => 3,
            'pending_partners' => 1,
            'active_deployments' => 3,
            'provinces_covered' => 2,
            'unlocated_partners' => 1,
            'programs_without_coordinator' => ['BSAB'],
            'evaluations_awaiting_review' => 0,
        ], $data['stats']);
        // Beta 2/6 is the most saturated, then Alpha 1/4, then Gamma 0/2.
        $this->assertSame(['Beta Inc', 'Alpha Corp', 'Gamma Coop'], array_column($data['saturation_top'], 'company'));
        $this->assertSame(['company' => 'Beta Inc', 'city' => 'Davao City', 'filled' => 2, 'slots' => 6, 'open' => 4], $data['saturation_top'][0]);
        $this->assertSame(['pending_partner_approvals', 'unlocated_partners', 'programs_without_coordinator', 'evaluations_awaiting_review'], array_keys($data['queue']));
    }

    public function test_coordinator_dashboard_counts_only_their_program(): void
    {
        $this->student($this->bsit, null, status: UserStatus::Pending);
        $this->student($this->bsab, null, status: UserStatus::Pending);
        $this->report('s1', 4, 'submitted'); // irrelevant to the coordinator's numbers

        $data = $this->dashboard($this->coordinator);

        $this->assertSame('coordinator', $data['role']);
        $this->assertSame([
            'active_interns' => 2,       // s1, s2 (b1 is BSAB)
            'pending_students' => 1,     // the BSAB one isn't theirs
            'open_slots' => 9,           // Alpha 3 + Beta 4 + Gamma 2
            'overdue_interns' => 2,      // s1 and s2 last reported in September
            'unplaced_students' => 1,    // s4 (withdrawn only); s5 completed last term
        ], $data['stats']);

        $rows = collect($data['interns_by_partner']);
        $this->assertSame(['Alpha Corp', 'Beta Inc', null], $rows->pluck('company')->all());
        $this->assertSame(['interns' => 1, 'open' => 3, 'overdue' => 1], $rows[0] ? collect($rows[0])->only(['interns', 'open', 'overdue'])->all() : []);
        $this->assertTrue($rows->last()['unplaced']);
        $this->assertSame(1, $rows->last()['interns']);
        $this->assertSame(['pending_students' => 1, 'overdue_interns' => 2, 'unplaced_students' => 1, 'evaluations_to_review' => 0], $data['queue']);
    }

    public function test_partner_dashboard(): void
    {
        $this->report('s1', 3, 'submitted');

        $data = $this->dashboard($this->alpha->user);

        $this->assertSame('partner', $data['role']);
        $this->assertSame([
            'interns_hosted' => 1,
            'slots' => 4,
            'reports_to_sign' => 1,
            'evaluations_open' => 2, // s1 has 120 signed hours: midterm and final both open, none submitted
            'programs_served' => ['BSIT'],
        ], $data['stats']);
        $this->assertSame([['name' => 'Ana One', 'program' => 'BSIT', 'signed_hours' => 120, 'required_hours' => 100, 'last_report' => '2026-09-21']], $data['interns']);
        $this->assertSame(['reports_to_sign' => 1, 'evaluations_open' => 2], $data['queue']);
    }

    public function test_student_dashboard_and_next_steps(): void
    {
        $this->report('s2', 3, 'returned');

        $data = $this->dashboard($this->s['s2']->user);

        $this->assertSame('student', $data['role']);
        $this->assertSame(30, $data['stats']['signed_hours']);
        $this->assertSame(100, $data['stats']['required_hours']);
        $this->assertSame('active', $data['stats']['deployment_status']);
        $this->assertSame(['company' => 'Beta Inc', 'city' => 'Davao City'], $data['stats']['partner']);
        $this->assertSame(['2026-09-21', 'returned'], [$data['stats']['last_report_period_end'], $data['stats']['last_report_status']]);
        $this->assertSame(3, $data['weeks_remaining_estimate']); // ceil(70 / 30 per signed week)
        $this->assertSame([
            ['type' => 'report_due', 'week' => 2, 'date' => '2026-09-14'],
            ['type' => 'report_returned', 'week' => 3, 'date' => '2026-09-21'],
        ], $data['next_steps']);
        $this->assertSame($this->coordinator->name, $data['placement']['coordinator']);
    }

    public function test_completed_student_sees_the_released_evaluation(): void
    {
        $data = $this->dashboard($this->s['s3']->user);

        $this->assertSame('completed', $data['stats']['deployment_status']);
        $this->assertNull($data['weeks_remaining_estimate']);
        $this->assertSame('evaluation_released', $data['next_steps'][0]['type']);
    }

    public function test_unplaced_student(): void
    {
        $data = $this->dashboard($this->s['s4']->user); // withdrawn placement only

        $this->assertNull($data['stats']);
        $this->assertSame([['type' => 'not_placed']], $data['next_steps']);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
