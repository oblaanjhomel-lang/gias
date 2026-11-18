<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\WeeklyReportStatus;
use App\Events\WeeklyReportReturned;
use App\Events\WeeklyReportSigned;
use App\Events\WeeklyReportSubmitted;
use App\Models\Deployment;
use App\Models\IndustryPartner;
use App\Models\Student;
use App\Models\WeeklyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

/**
 * "Today" is Tue 17 Nov 2026 (Asia/Manila). Deployments start Tue 1 Sep 2026, so
 * week 12 = 17–23 Nov (started today) and week 13 = 24–30 Nov (future).
 */
class WeeklyReportTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    private Student $student;

    private IndustryPartner $partner;

    private Deployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-17 02:00:00'); // 10:00 in Manila
        $this->setUpFixtures();

        $this->student = $this->student($this->bsit, self::TAGUM, name: 'Juan Intern');
        $this->partner = $this->partner('Host Co', self::TAGUM, 3);
        $this->deployment = $this->deploy($this->student, $this->partner);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'week_number' => 1,
            'daily_hours' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 8, 'fri' => 7.5, 'sat' => 0, 'sun' => 0],
            'tasks' => 'Set up the network printers and documented the IP plan.',
            'learnings' => 'How DHCP reservations work.',
        ], $overrides);
    }

    private function asStudent(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->student->user, 'web');
    }

    private function asPartner(?IndustryPartner $partner = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(($partner ?? $this->partner)->user, 'web');
    }

    // Weeks and periods

    public function test_the_server_computes_the_week_period(): void
    {
        $this->asStudent()->postJson('/api/student/reports', $this->payload(['week_number' => 1]))
            ->assertCreated()
            ->assertJsonPath('data.period_start', '2026-09-01')
            ->assertJsonPath('data.period_end', '2026-09-07')
            ->assertJsonPath('data.status', 'draft');

        $this->postJson('/api/student/reports', $this->payload([
            'week_number' => 12, 'period_start' => '2020-01-01', 'period_end' => '2020-01-07', // ignored
        ]))->assertCreated()
            ->assertJsonPath('data.period_start', '2026-11-17')
            ->assertJsonPath('data.period_end', '2026-11-23');
    }

    public function test_future_weeks_are_rejected(): void
    {
        $this->asStudent()->postJson('/api/student/reports', $this->payload(['week_number' => 13]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['week_number' => 'Week 13 starts on 24 Nov 2026']);
    }

    public function test_only_an_active_deployment_takes_reports(): void
    {
        $this->deployment->update(['status' => DeploymentStatus::Withdrawn]);

        $this->asStudent()->postJson('/api/student/reports', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deployment');
    }

    public function test_daily_hours_validation_and_server_side_total(): void
    {
        $this->asStudent();

        $this->postJson('/api/student/reports', $this->payload(['daily_hours' => ['mon' => 12.5, 'tue' => 7.3]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['daily_hours.mon', 'daily_hours.tue' => 'steps of 0.5']);

        $this->postJson('/api/student/reports', $this->payload([
            'daily_hours' => ['mon' => 12, 'tue' => 12, 'wed' => 12, 'thu' => 12, 'fri' => 12, 'sat' => 1],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['daily_hours' => 'at most 60 hours']);

        $this->postJson('/api/student/reports', $this->payload(['tasks' => 'Too short']))
            ->assertUnprocessable()->assertJsonValidationErrors('tasks');

        $this->postJson('/api/student/reports', $this->payload(['total_hours' => 999]))
            ->assertCreated()
            ->assertJsonPath('data.total_hours', 39.5); // 8+8+8+8+7.5, computed by the server

        $this->assertEquals(39.5, WeeklyReport::sole()->total_hours);
    }

    public function test_saving_the_same_week_again_updates_the_draft(): void
    {
        $this->asStudent();
        $first = $this->postJson('/api/student/reports', $this->payload())->json('data.id');
        $this->postJson('/api/student/reports', $this->payload(['tasks' => 'Updated tasks: cabling and labelling.']))
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertSame(1, WeeklyReport::count());
    }

    // Status flow

    public function test_the_full_flow_with_a_return_and_resubmit(): void
    {
        Event::fake([WeeklyReportSubmitted::class, WeeklyReportSigned::class, WeeklyReportReturned::class]);

        $id = $this->asStudent()->postJson('/api/student/reports', $this->payload())->json('data.id');
        $this->postJson("/api/student/reports/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        Event::assertDispatched(WeeklyReportSubmitted::class, fn ($e) => $e->report->id === $id);

        // Submitted: the student can no longer edit or delete it.
        $this->putJson("/api/student/reports/{$id}", $this->payload())->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->deleteJson("/api/student/reports/{$id}")->assertUnprocessable();

        // Partner returns it (a note is required).
        $this->asPartner()->postJson("/api/partner/reports/{$id}/return")->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/partner/reports/{$id}/return", ['note' => 'Please list what you did each day.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.return_note', 'Please list what you did each day.');
        Event::assertDispatched(WeeklyReportReturned::class);

        // Student edits and resubmits.
        $this->asStudent()->putJson("/api/student/reports/{$id}", $this->payload(['tasks' => 'Mon: cabling. Tue: printers. Wed–Fri: helpdesk tickets.']))
            ->assertOk()
            ->assertJsonPath('data.status', 'returned');
        $this->postJson("/api/student/reports/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        // Partner countersigns: final.
        $this->asPartner()->postJson("/api/partner/reports/{$id}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed');
        Event::assertDispatched(WeeklyReportSigned::class);
        $this->assertSame($this->partner->user_id, WeeklyReport::find($id)->signed_by);

        $this->postJson("/api/partner/reports/{$id}/sign")->assertUnprocessable();
        $this->postJson("/api/partner/reports/{$id}/return", ['note' => 'Changed my mind about it.'])->assertUnprocessable();
        $this->asStudent()->putJson("/api/student/reports/{$id}", $this->payload())->assertUnprocessable();
        $this->deleteJson("/api/student/reports/{$id}")->assertUnprocessable();
        $this->postJson('/api/student/reports', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('week_number');
    }

    public function test_drafts_can_be_deleted_and_drafts_cannot_be_signed(): void
    {
        $id = $this->asStudent()->postJson('/api/student/reports', $this->payload())->json('data.id');

        $this->asPartner()->postJson("/api/partner/reports/{$id}/sign")->assertUnprocessable();
        $this->asStudent()->deleteJson("/api/student/reports/{$id}")->assertNoContent();
        $this->assertSame(0, WeeklyReport::count());
    }

    // Ownership

    public function test_students_and_partners_only_touch_their_own_reports(): void
    {
        $id = $this->asStudent()->postJson('/api/student/reports', $this->payload())->json('data.id');
        $this->postJson("/api/student/reports/{$id}/submit")->assertOk();

        $otherStudent = $this->student($this->bsit, self::TAGUM);
        $this->deploy($otherStudent, $this->partner);
        $this->app['auth']->forgetGuards();
        $this->actingAs($otherStudent->user, 'web');
        $this->putJson("/api/student/reports/{$id}", $this->payload())->assertForbidden();
        $this->postJson("/api/student/reports/{$id}/submit")->assertForbidden();
        $this->deleteJson("/api/student/reports/{$id}")->assertForbidden();

        $otherSite = $this->partner('Other Site', self::PANABO, 2);
        $this->asPartner($otherSite)->postJson("/api/partner/reports/{$id}/sign")->assertForbidden();
        $this->getJson("/api/partner/reports/{$id}")->assertForbidden();
        $this->getJson('/api/partner/reports')->assertOk()->assertJsonCount(0, 'data');

        $this->asStudent()->postJson("/api/partner/reports/{$id}/sign")->assertForbidden(); // role
    }

    public function test_partner_report_list_with_counts(): void
    {
        $this->asStudent();
        $a = $this->postJson('/api/student/reports', $this->payload(['week_number' => 1]))->json('data.id');
        $b = $this->postJson('/api/student/reports', $this->payload(['week_number' => 2]))->json('data.id');
        $this->postJson('/api/student/reports', $this->payload(['week_number' => 3])); // draft: never shown
        $this->postJson("/api/student/reports/{$a}/submit");
        $this->postJson("/api/student/reports/{$b}/submit");
        $this->asPartner()->postJson("/api/partner/reports/{$b}/sign");

        $this->getJson('/api/partner/reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a)
            ->assertJsonPath('data.0.intern', ['name' => 'Juan Intern', 'program' => 'BSIT'])
            ->assertJsonPath('meta.counts', ['submitted' => 1, 'signed' => 1, 'returned' => 0]);

        $this->getJson('/api/partner/reports?status=signed')->assertJsonPath('data.0.id', $b);
        $this->getJson('/api/partner/reports?status=draft')->assertUnprocessable();
    }

    public function test_students_see_their_deployment(): void
    {
        $this->coordinator->update(['name' => 'Coord Person']);
        $this->asStudent()->postJson('/api/student/reports', $this->payload());

        $this->getJson('/api/student/deployment')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.partner.company_name', 'Host Co')
            ->assertJsonPath('data.partner.contact_person', $this->partner->contact_person)
            ->assertJsonPath('data.coordinator', ['name' => 'Coord Person', 'email' => $this->coordinator->email])
            ->assertJsonPath('data.progress.required_hours', 486)
            ->assertJsonCount(12, 'data.weeks') // weeks 1–12 have started
            ->assertJsonPath('data.weeks.0.status', 'draft')
            ->assertJsonCount(1, 'data.reports');

        $neverPlaced = $this->student($this->bsit, self::TAGUM);
        $this->app['auth']->forgetGuards();
        $this->actingAs($neverPlaced->user, 'web')->getJson('/api/student/deployment')
            ->assertNotFound()
            ->assertJsonPath('message', 'You haven’t been placed yet. Your OJT Coordinator will assign your Industry Partner.');
    }

    public function test_a_returned_report_keeps_its_status_until_resubmitted(): void
    {
        $report = WeeklyReport::create([
            'deployment_id' => $this->deployment->id, 'week_number' => 2, 'period_start' => '2026-09-08', 'period_end' => '2026-09-14',
            'daily_hours' => ['mon' => 8], 'total_hours' => 8, 'tasks' => 'Some tasks for this week here.',
            'status' => WeeklyReportStatus::Returned, 'return_note' => 'Add the dates please.',
        ]);

        $this->asStudent()->postJson('/api/student/reports', $this->payload(['week_number' => 2]))
            ->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.status', 'returned');
    }
}
