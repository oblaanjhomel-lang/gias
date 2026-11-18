<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Enums\WeeklyReportStatus;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\IndustryPartner;
use App\Models\Student;
use App\Models\WeeklyReport;
use App\Notifications\EvaluationsAwaitingReviewReminder;
use App\Notifications\EvaluationWindowOpened;
use App\Notifications\OverdueInternsDigest;
use App\Notifications\ReportOverdueReminder;
use App\Notifications\ReportsAwaitingSignatureReminder;
use App\Services\ReminderService;
use App\Services\WeeklyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

/**
 * Pinned to Tuesday 17 Nov 2026, 10:00 Manila. The deployment starts 1 Sep with no reports,
 * so it is overdue. BSIT requires 100 hours: the midterm window opens at 50 signed hours.
 */
class ReminderTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    private IndustryPartner $host;

    private Student $student;

    private Deployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-17 02:00:00');
        $this->setUpFixtures();
        $this->bsit->update(['required_ojt_hours' => 100]);
        Notification::fake();

        $this->host = $this->partner('Host Co', self::TAGUM, 5);
        $this->student = $this->student($this->bsit, self::TAGUM, name: 'Juan Intern');
        $this->deployment = $this->deploy($this->student, $this->host);
    }

    /** @return array<string, int> */
    private function remind(): array
    {
        return app(ReminderService::class)->run();
    }

    private function report(int $week, float $hours, WeeklyReportStatus $status, ?string $submittedAt = null): void
    {
        [$start, $end] = WeeklyReportService::periodFor($this->deployment->start_date, $week);
        WeeklyReport::unguarded(fn () => WeeklyReport::create([
            'deployment_id' => $this->deployment->id, 'week_number' => $week, 'period_start' => $start, 'period_end' => $end,
            'daily_hours' => ['mon' => $hours], 'total_hours' => $hours, 'tasks' => 'Routine tasks for the week.',
            'status' => $status, 'submitted_at' => $submittedAt ?? now(),
        ]));
    }

    public function test_overdue_reminder_fires_once_per_iso_week(): void
    {
        $this->assertSame(1, $this->remind()['overdue_reports']);
        Notification::assertSentToTimes($this->student->user, ReportOverdueReminder::class, 1);

        $this->travel(1)->days();
        $this->assertSame(0, $this->remind()['overdue_reports'], 'same ISO week: deduped');

        $this->travel(6)->days(); // Tue 24 Nov, next ISO week
        $this->assertSame(1, $this->remind()['overdue_reports']);
        Notification::assertSentToTimes($this->student->user, ReportOverdueReminder::class, 2);
    }

    public function test_up_to_date_students_get_no_overdue_reminder(): void
    {
        $this->deployment->update(['start_date' => '2026-11-09']);

        $this->assertSame(0, $this->remind()['overdue_reports']);
    }

    public function test_signature_reminder_for_reports_waiting_more_than_three_days_every_three_days(): void
    {
        $this->report(1, 8, WeeklyReportStatus::Submitted, now()->subDay()->toDateTimeString());
        $this->assertSame(0, $this->remind()['reports_awaiting_signature'], 'only 1 day old');

        $this->report(2, 8, WeeklyReportStatus::Submitted, now()->subDays(5)->toDateTimeString());
        $this->assertSame(1, $this->remind()['reports_awaiting_signature']);
        Notification::assertSentTo($this->host->user, ReportsAwaitingSignatureReminder::class);

        $this->assertSame(0, $this->remind()['reports_awaiting_signature'], 'deduped within the bucket');

        $this->travel(3)->days();
        $this->assertSame(1, $this->remind()['reports_awaiting_signature'], 'next 3-day bucket');
    }

    public function test_evaluation_window_reminder_fires_once_per_type(): void
    {
        $this->report(1, 40, WeeklyReportStatus::Signed);
        $this->assertSame(0, $this->remind()['evaluation_windows_opened'], '40 < 50 hours');

        $this->report(2, 20, WeeklyReportStatus::Signed);
        $this->assertSame(1, $this->remind()['evaluation_windows_opened']);
        Notification::assertSentTo($this->host->user, EvaluationWindowOpened::class);

        $this->travel(10)->days();
        $this->assertSame(0, $this->remind()['evaluation_windows_opened'], 'never repeated');

        $this->report(3, 40, WeeklyReportStatus::Signed); // 100 hours: final opens
        $this->assertSame(1, $this->remind()['evaluation_windows_opened']);
    }

    public function test_no_window_reminder_once_the_evaluation_was_submitted(): void
    {
        $this->report(1, 60, WeeklyReportStatus::Signed);
        Evaluation::unguarded(fn () => Evaluation::create([
            'deployment_id' => $this->deployment->id, 'type' => 'midterm', 'ratings' => ['quality' => 4],
            'status' => 'submitted', 'submitted_at' => now(),
        ]));

        $this->assertSame(0, $this->remind()['evaluation_windows_opened']);
    }

    public function test_review_reminder_goes_to_the_coordinator_every_three_days(): void
    {
        Evaluation::unguarded(fn () => Evaluation::create([
            'deployment_id' => $this->deployment->id, 'type' => 'midterm', 'ratings' => ['quality' => 4],
            'status' => 'submitted', 'submitted_at' => now()->subDays(4),
        ]));

        $this->assertSame(1, $this->remind()['evaluations_awaiting_review']);
        Notification::assertSentTo($this->coordinator, EvaluationsAwaitingReviewReminder::class, fn ($n) => $n->actionPath === '/coordinator/evaluations');
        Notification::assertNotSentTo($this->admin, EvaluationsAwaitingReviewReminder::class);

        $this->assertSame(0, $this->remind()['evaluations_awaiting_review']);
        $this->travel(3)->days();
        $this->assertSame(1, $this->remind()['evaluations_awaiting_review']);
    }

    public function test_overdue_digest_only_on_mondays_once_per_week(): void
    {
        $this->assertSame(0, $this->remind()['overdue_interns_digest'], 'Tuesday');

        $this->travelTo('2026-11-23 00:30:00'); // Monday 08:30 Manila
        $this->assertSame(1, $this->remind()['overdue_interns_digest']);
        Notification::assertSentTo($this->coordinator, OverdueInternsDigest::class, fn ($n) => str_contains($n->message, 'Juan Intern'));

        $this->assertSame(0, $this->remind()['overdue_interns_digest'], 'same Monday: deduped');
    }

    public function test_inactive_users_get_no_reminders(): void
    {
        $this->student->user->update(['status' => UserStatus::Inactive]);

        $this->assertSame(0, $this->remind()['overdue_reports']);
        $this->assertDatabaseCount('notification_dedupes', 0);
    }

    public function test_command_prints_counts_per_type(): void
    {
        $this->artisan('gias:send-reminders')
            ->expectsOutputToContain('overdue_reports')
            ->expectsOutputToContain('overdue_interns_digest')
            ->expectsOutputToContain('Total sent: 1')
            ->assertSuccessful();
    }
}
