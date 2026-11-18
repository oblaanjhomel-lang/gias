<?php

namespace Tests\Feature;

use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WeeklyReportStatus;
use App\Events\DeploymentCompleted;
use App\Events\EvaluationReturned;
use App\Events\EvaluationReviewed;
use App\Events\EvaluationSubmitted;
use App\Events\RegistrationReturned;
use App\Events\RegistrationSubmitted;
use App\Events\WeeklyReportReturned;
use App\Events\WeeklyReportSigned;
use App\Events\WeeklyReportSubmitted;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\IndustryPartner;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Notifications\DeploymentCompletedNotice;
use App\Notifications\EvaluationAwaitingReview;
use App\Notifications\EvaluationReleased;
use App\Notifications\EvaluationReturnedToPartner;
use App\Notifications\NewRegistrationPending;
use App\Notifications\PlacementWithdrawn;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationReceived;
use App\Notifications\RegistrationReturned as RegistrationReturnedNotification;
use App\Notifications\StudentPlaced;
use App\Notifications\WeeklyReportAwaitingSignature;
use App\Notifications\WeeklyReportReturnedToStudent;
use App\Notifications\WeeklyReportSigned as WeeklyReportSignedNotification;
use App\Services\PlacementService;
use App\Services\WeeklyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

/**
 * Module 8: each event reaches the right people with the right notification; the actor is
 * never notified; inactive recipients are skipped; channels follow the email preference.
 */
class NotificationEventsTest extends TestCase
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

        $this->host = $this->partner('Host Co', self::TAGUM, 5);
        $this->student = $this->student($this->bsit, self::TAGUM, name: 'Juan Intern');
        $this->deployment = $this->deploy($this->student, $this->host);
    }

    private function report(WeeklyReportStatus $status = WeeklyReportStatus::Submitted): WeeklyReport
    {
        [$start, $end] = WeeklyReportService::periodFor($this->deployment->start_date, 1);

        return WeeklyReport::create([
            'deployment_id' => $this->deployment->id, 'week_number' => 1, 'period_start' => $start, 'period_end' => $end,
            'daily_hours' => ['mon' => 8], 'total_hours' => 8, 'tasks' => 'Routine tasks for the week.', 'status' => $status,
        ]);
    }

    private function evaluation(EvaluationType $type, ?Deployment $deployment = null): Evaluation
    {
        return Evaluation::unguarded(fn () => Evaluation::create([
            'deployment_id' => ($deployment ?? $this->deployment)->id, 'type' => $type,
            'ratings' => ['quality' => 4], 'status' => 'submitted', 'submitted_at' => now(),
        ]));
    }

    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user);
    }

    // ---- Registration ----

    public function test_registration_submitted_notifies_registrant_and_the_programs_coordinator(): void
    {
        Notification::fake();
        $pending = $this->student($this->bsit, null, status: UserStatus::Pending)->user;

        RegistrationSubmitted::dispatch($pending);

        Notification::assertSentTo($pending, RegistrationReceived::class, fn ($n) => str_contains($n->reviewer, 'Coordinator'));
        Notification::assertSentTo($this->coordinator, NewRegistrationPending::class, fn ($n) => $n->actionPath === '/coordinator/approvals');
        Notification::assertNotSentTo($this->admin, NewRegistrationPending::class);
    }

    public function test_registration_falls_back_to_admins_when_the_program_has_no_coordinator(): void
    {
        Notification::fake();
        $pending = $this->student($this->bsab, null, status: UserStatus::Pending)->user;

        RegistrationSubmitted::dispatch($pending);

        Notification::assertSentTo($this->admin, NewRegistrationPending::class, fn ($n) => $n->actionPath === '/admin/approvals');
        Notification::assertNotSentTo($this->coordinator, NewRegistrationPending::class);
    }

    public function test_returned_registration_mails_the_pending_user(): void
    {
        Notification::fake();
        $pending = $this->student($this->bsit, null, status: UserStatus::Pending)->user;
        $this->as($this->coordinator);

        RegistrationReturned::dispatch($pending, 'Please use your school email.');

        Notification::assertSentTo($pending, RegistrationReturnedNotification::class, fn ($n, $channels) => $channels === ['mail']);
    }

    // ---- Placement (through PlacementService) ----

    public function test_placement_and_withdrawal_notify_student_and_partner_but_not_the_coordinator(): void
    {
        Notification::fake();
        $this->deployment->delete();
        $this->as($this->coordinator);
        $placement = app(PlacementService::class);

        $deployment = $placement->place($this->coordinator, $this->student->user, $this->host->user, 'cos', '2026-11-23');

        Notification::assertSentTo($this->student->user, StudentPlaced::class, fn ($n) => $n->actionPath === '/student/deployment');
        Notification::assertSentTo($this->host->user, StudentPlaced::class);
        Notification::assertNotSentTo($this->coordinator, StudentPlaced::class);

        $placement->withdraw($this->coordinator, $deployment);

        Notification::assertSentTo($this->student->user, PlacementWithdrawn::class);
        Notification::assertSentTo($this->host->user, PlacementWithdrawn::class);
        Notification::assertNotSentTo($this->coordinator, PlacementWithdrawn::class);
    }

    public function test_a_failed_batch_placement_sends_nothing(): void
    {
        Notification::fake();
        $this->deployment->delete();
        $other = $this->student($this->bsit, self::TAGUM);
        $full = $this->partner('Full Co', self::TAGUM, 0);

        try {
            app(PlacementService::class)->placeBatch($this->coordinator, [
                ['student_id' => $this->student->user_id, 'partner_id' => $this->host->user_id, 'start_date' => '2026-11-23'],
                ['student_id' => $other->user_id, 'partner_id' => $full->user_id, 'start_date' => '2026-11-23'],
            ]);
            $this->fail('Batch should have failed.');
        } catch (ValidationException) {
        }

        Notification::assertNothingSent();
    }

    // ---- Weekly reports ----

    public function test_weekly_report_events_reach_the_other_party_only(): void
    {
        Notification::fake();
        $report = $this->report();

        $this->as($this->student->user);
        WeeklyReportSubmitted::dispatch($report);
        Notification::assertSentTo($this->host->user, WeeklyReportAwaitingSignature::class);
        Notification::assertNotSentTo($this->student->user, WeeklyReportAwaitingSignature::class);

        $this->as($this->host->user);
        WeeklyReportSigned::dispatch($report);
        WeeklyReportReturned::dispatch($report);
        Notification::assertSentTo($this->student->user, WeeklyReportSignedNotification::class);
        Notification::assertSentTo($this->student->user, WeeklyReportReturnedToStudent::class);
        Notification::assertNotSentTo($this->host->user, WeeklyReportSignedNotification::class);
    }

    public function test_the_actor_is_never_notified(): void
    {
        Notification::fake();
        $this->as($this->host->user); // the partner is both actor and recipient here

        WeeklyReportSubmitted::dispatch($this->report());

        Notification::assertNothingSent();
    }

    public function test_inactive_recipients_are_skipped(): void
    {
        Notification::fake();
        $this->student->user->update(['status' => UserStatus::Inactive]);

        WeeklyReportSigned::dispatch($this->report(WeeklyReportStatus::Signed));

        Notification::assertNothingSent();
    }

    public function test_deployment_completed_notifies_student_and_partner(): void
    {
        Notification::fake();
        $this->as($this->coordinator);

        DeploymentCompleted::dispatch($this->deployment);

        Notification::assertSentTo($this->student->user, DeploymentCompletedNotice::class);
        Notification::assertSentTo($this->host->user, DeploymentCompletedNotice::class);
        Notification::assertNotSentTo($this->coordinator, DeploymentCompletedNotice::class);
    }

    // ---- Evaluations ----

    public function test_submitted_evaluation_goes_to_the_coordinator_or_admin_fallback(): void
    {
        Notification::fake();
        $this->as($this->host->user);

        EvaluationSubmitted::dispatch($this->evaluation(EvaluationType::Midterm));
        Notification::assertSentTo($this->coordinator, EvaluationAwaitingReview::class, fn ($n) => $n->actionPath === '/coordinator/evaluations');
        Notification::assertNotSentTo($this->admin, EvaluationAwaitingReview::class);

        $bsabDeployment = $this->deploy($this->student($this->bsab, self::TAGUM), $this->partner('Farm Co', self::TAGUM, 2));
        EvaluationSubmitted::dispatch($this->evaluation(EvaluationType::Midterm, $bsabDeployment));
        Notification::assertSentTo($this->admin, EvaluationAwaitingReview::class, fn ($n) => $n->actionPath === '/admin/evaluations');
    }

    public function test_returned_evaluation_goes_to_partner_and_only_final_reviews_are_released_to_the_student(): void
    {
        Notification::fake();
        $this->as($this->coordinator);

        EvaluationReturned::dispatch($this->evaluation(EvaluationType::Midterm));
        Notification::assertSentTo($this->host->user, EvaluationReturnedToPartner::class);

        EvaluationReviewed::dispatch(Evaluation::where('type', 'midterm')->first());
        Notification::assertNotSentTo($this->student->user, EvaluationReleased::class);

        EvaluationReviewed::dispatch($this->evaluation(EvaluationType::Final));
        Notification::assertSentTo($this->student->user, EvaluationReleased::class);
    }

    // ---- Channels ----

    public function test_channels_follow_the_email_preference(): void
    {
        $user = $this->student->user;
        $placed = StudentPlaced::forStudent($this->deployment);

        $this->assertSame(['database', 'mail'], $placed->via($user));

        $user->update(['email_notifications' => false]);
        $this->assertSame(['database'], $placed->via($user));
        $this->assertSame(['database', 'mail'], (new RegistrationApproved)->via($user), 'mandatory mail ignores the opt-out');
        $this->assertSame(['mail'], (new RegistrationReturnedNotification('x'))->via($user));
        $this->assertSame(['mail'], (new RegistrationReceived('the GIAS Admin'))->via($user));
    }

    public function test_mail_only_notifications_leave_no_database_row_and_others_do(): void
    {
        $user = $this->student->user;

        $user->notify(new RegistrationReturnedNotification('Missing documents.'));
        $this->assertDatabaseCount('notifications', 0);

        $user->notify(StudentPlaced::forStudent($this->deployment));
        $this->assertDatabaseCount('notifications', 1);
        $data = $user->notifications()->first()->data;
        $this->assertSame('deployment', $data['category']);
        $this->assertSame(['category', 'message', 'action_path', 'action_label', 'meta'], array_keys($data));
    }

    public function test_mail_has_greeting_button_and_salutation(): void
    {
        $user = User::factory()->role(UserRole::Student)->create(['name' => 'Maria Santos']);
        $mail = (new RegistrationApproved)->toMail($user);

        $this->assertSame('Hi Maria,', $mail->greeting);
        $this->assertSame('http://localhost:5173/login', $mail->actionUrl);
        $this->assertSame('— GIAS, Davao del Norte State College', $mail->salutation);
    }

    public function test_notifications_are_queued_on_the_notifications_queue(): void
    {
        Queue::fake();

        $this->student->user->notify(StudentPlaced::forStudent($this->deployment));

        Queue::assertPushedOn('notifications', SendQueuedNotifications::class);
    }
}
