<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\UserRole;
use App\Enums\WeeklyReportStatus;
use App\Events\EvaluationReturned;
use App\Events\EvaluationReviewed;
use App\Events\EvaluationSubmitted;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\IndustryPartner;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

/**
 * BSIT requires 100 hours here, so midterm opens at 50 and final at 90 signed hours.
 */
class EvaluationTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    private const FULL = ['quality' => 5, 'technical' => 4, 'attendance' => 4, 'initiative' => 5, 'communication' => 3, 'teamwork' => 4];

    private IndustryPartner $host;

    private Student $student;

    private Deployment $deployment;

    private int $nextWeek = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-17 02:00:00');
        $this->setUpFixtures();
        $this->bsit->update(['required_ojt_hours' => 100]);

        $this->host = $this->partner('Host Co', self::TAGUM, 5);
        $this->student = $this->student($this->bsit, self::TAGUM, name: 'Juan Intern');
        $this->deployment = $this->deploy($this->student, $this->host);
    }

    private function hours(float $hours, WeeklyReportStatus $status = WeeklyReportStatus::Signed, ?Deployment $deployment = null): void
    {
        $deployment ??= $this->deployment;
        [$start, $end] = WeeklyReportService::periodFor($deployment->start_date, $this->nextWeek);
        WeeklyReport::create([
            'deployment_id' => $deployment->id, 'week_number' => $this->nextWeek++, 'period_start' => $start, 'period_end' => $end,
            'daily_hours' => ['mon' => $hours], 'total_hours' => $hours, 'tasks' => 'Routine tasks for the week.', 'status' => $status,
        ]);
    }

    private function asPartner(?IndustryPartner $partner = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs(($partner ?? $this->host)->user, 'web');
    }

    private function as(mixed $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function url(string $type = 'final', string $suffix = ''): string
    {
        return "/api/partner/evaluations/{$this->deployment->id}/{$type}{$suffix}";
    }

    /** A submitted final evaluation with FULL ratings. */
    private function submittedFinal(): Evaluation
    {
        $this->hours(45);
        $this->hours(45);
        $this->asPartner()->putJson($this->url(), ['ratings' => self::FULL, 'comment' => 'Reliable and eager to learn.'])->assertOk();
        $this->postJson($this->url(suffix: '/submit'))->assertOk();

        return Evaluation::sole();
    }

    // Opening thresholds (signed hours only)

    public function test_midterm_opens_at_50_percent_and_final_at_90(): void
    {
        $this->hours(49);
        $this->hours(40, WeeklyReportStatus::Submitted); // not signed: doesn't count

        $this->asPartner()->getJson($this->url('midterm'))
            ->assertOk()
            ->assertJsonPath('data.open', ['open' => false, 'threshold_hours' => 50, 'signed_hours' => 49, 'required_hours' => 100]);
        $this->putJson($this->url('midterm'), ['ratings' => ['quality' => 4]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'The midterm evaluation opens at 50 signed hours (now 49).']);

        $this->hours(1); // 50
        $this->getJson($this->url('midterm'))->assertJsonPath('data.open.open', true);
        $this->putJson($this->url('midterm'), ['ratings' => ['quality' => 4]])->assertOk();

        $this->hours(39); // 89
        $this->getJson($this->url('final'))->assertJsonPath('data.open.open', false);
        $this->hours(1); // 90
        $this->getJson($this->url('final'))->assertJsonPath('data.open.open', true);

        $this->getJson('/api/partner/evaluations')
            ->assertOk()
            ->assertJsonPath('data.0.midterm', ['status' => 'draft', 'open' => true, 'threshold_hours' => 50])
            ->assertJsonPath('data.0.final', ['status' => null, 'open' => true, 'threshold_hours' => 90])
            ->assertJsonPath('meta', ['finals_submitted' => 0, 'total' => 1]);
    }

    public function test_a_partner_cannot_evaluate_another_site_a_withdrawn_deployment_or_a_closed_type(): void
    {
        $this->hours(60);

        $this->asPartner($this->partner('Other Site', self::PANABO, 2))
            ->putJson($this->url('midterm'), ['ratings' => ['quality' => 4]])
            ->assertForbidden();
        $this->getJson($this->url('midterm'))->assertForbidden();

        $this->asPartner()->putJson($this->url('final'), ['ratings' => ['quality' => 4]])
            ->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->deployment->update(['status' => DeploymentStatus::Withdrawn]);
        $this->putJson($this->url('midterm'), ['ratings' => ['quality' => 4]])
            ->assertUnprocessable()->assertJsonValidationErrors('deployment');

        $this->as($this->student->user)->putJson($this->url('midterm'), ['ratings' => []])->assertForbidden(); // role
    }

    // Validation

    public function test_partial_drafts_are_fine_but_submit_needs_all_six_whole_numbers(): void
    {
        $this->hours(60);
        $this->asPartner();

        $this->putJson($this->url('midterm'), ['ratings' => ['quality' => 6]])->assertUnprocessable()->assertJsonValidationErrors('ratings.quality');
        $this->putJson($this->url('midterm'), ['ratings' => ['quality' => 3.5]])->assertUnprocessable()->assertJsonValidationErrors('ratings.quality');
        $this->putJson($this->url('midterm'), ['ratings' => ['punctuality' => 3]])->assertUnprocessable()->assertJsonValidationErrors('ratings');

        $partial = array_slice(self::FULL, 0, 5, true);
        $this->putJson($this->url('midterm'), ['ratings' => $partial])
            ->assertOk()
            ->assertJsonPath('data.evaluation.status', 'draft')
            ->assertJsonPath('data.evaluation.rated_count', 5);

        $this->postJson($this->url('midterm', '/submit'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ratings' => 'Rate all 6 criteria before submitting (5 of 6 done).']);
    }

    // Rounding

    public function test_partner_mean_and_final_rating_rounding(): void
    {
        $evaluation = $this->submittedFinal();

        $this->assertSame('4.17', $evaluation->partner_mean); // 25 / 6 = 4.1667
        $this->assertSame(['quality', 'technical', 'attendance', 'initiative', 'communication', 'teamwork'], array_column($evaluation->criteria_snapshot, 'key'));

        $this->as($this->coordinator)
            ->putJson("/api/evaluations/{$evaluation->id}/coordinator-rating", ['rating' => 5, 'note' => 'Complete requirements.'])
            ->assertOk();
        $this->postJson("/api/evaluations/{$evaluation->id}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.partner_mean', 4.17)
            ->assertJsonPath('data.final_rating', 4.42) // 0.7 × 4.17 + 0.3 × 5 = 4.419
            ->assertJsonPath('data.final_descriptor', 'Very satisfactory');

        $this->assertSame('4.42', $evaluation->fresh()->final_rating);
    }

    // Status flow

    public function test_review_reopen_and_return_transitions(): void
    {
        Event::fake([EvaluationSubmitted::class, EvaluationReviewed::class, EvaluationReturned::class]);
        $evaluation = $this->submittedFinal();
        Event::assertDispatched(EvaluationSubmitted::class, fn ($e) => $e->evaluation->id === $evaluation->id);

        // Submitted: the partner can't edit any more.
        $this->putJson($this->url(), ['ratings' => self::FULL])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->as($this->coordinator);
        $this->postJson("/api/evaluations/{$evaluation->id}/review")
            ->assertUnprocessable()->assertJsonValidationErrors(['coordinator_rating' => 'Add your rating']);
        $this->putJson("/api/evaluations/{$evaluation->id}/coordinator-rating", ['rating' => 6])->assertUnprocessable();
        $this->putJson("/api/evaluations/{$evaluation->id}/coordinator-rating", ['rating' => 4])->assertOk();
        $this->postJson("/api/evaluations/{$evaluation->id}/review")->assertOk();
        Event::assertDispatched(EvaluationReviewed::class);

        $this->postJson("/api/evaluations/{$evaluation->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.final_rating', null)
            ->assertJsonPath('data.reviewed_at', null);

        $this->postJson("/api/evaluations/{$evaluation->id}/return")->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/evaluations/{$evaluation->id}/return", ['note' => 'Please explain the low communication score.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned');
        Event::assertDispatched(EvaluationReturned::class);
        $this->postJson("/api/evaluations/{$evaluation->id}/reopen")->assertUnprocessable();

        // Returned: the partner can edit and resubmit.
        $this->asPartner()->getJson($this->url())->assertJsonPath('data.evaluation.return_note', 'Please explain the low communication score.');
        $this->putJson($this->url(), ['ratings' => ['communication' => 4] + self::FULL, 'comment' => 'Communication improved in week 9.'])
            ->assertOk()
            ->assertJsonPath('data.evaluation.status', 'returned');
        $this->postJson($this->url(suffix: '/submit'))->assertOk()->assertJsonPath('data.evaluation.status', 'submitted');
        $this->assertSame('4.33', $evaluation->fresh()->partner_mean); // 26 / 6
    }

    public function test_a_completed_deployments_evaluation_cannot_be_reopened(): void
    {
        $evaluation = $this->submittedFinal();
        $evaluation->update(['coordinator_rating' => 5]);
        $this->as($this->coordinator)->postJson("/api/evaluations/{$evaluation->id}/review")->assertOk();
        $this->deployment->update(['status' => DeploymentStatus::Completed]);

        $this->postJson("/api/evaluations/{$evaluation->id}/reopen")
            ->assertUnprocessable()->assertJsonValidationErrors(['status' => 'already completed']);
    }

    // Scope

    public function test_coordinators_only_review_their_programs_evaluations(): void
    {
        $evaluation = $this->submittedFinal();
        $otherCoordinator = User::factory()->role(UserRole::Coordinator)->create(['program_id' => $this->bsab->id]);

        $this->as($otherCoordinator);
        $this->getJson("/api/evaluations/{$evaluation->id}")->assertForbidden();
        $this->putJson("/api/evaluations/{$evaluation->id}/coordinator-rating", ['rating' => 3])->assertForbidden();
        $this->getJson('/api/evaluations?type=final')->assertOk()->assertJsonCount(0, 'data');

        $this->as($this->admin)->getJson("/api/evaluations/{$evaluation->id}")
            ->assertOk()
            ->assertJsonPath('data.deployment.student.name', 'Juan Intern');
        $this->getJson('/api/evaluations?type=final')
            ->assertOk()
            ->assertJsonPath('data.0.evaluation.status', 'submitted')
            ->assertJsonPath('data.0.evaluation.partner_mean', 4.17)
            ->assertJsonPath('meta.counts', ['to_review' => 1, 'reviewed' => 0, 'not_submitted' => 0]);
    }

    public function test_the_queue_lists_not_started_interns_and_hides_draft_ratings(): void
    {
        $this->hours(60);
        $other = $this->deploy($this->student($this->bsit, self::TAGUM, name: 'Ana NotStarted'), $this->host);
        $this->asPartner()->putJson($this->url('midterm'), ['ratings' => ['quality' => 2]]);

        $rows = $this->as($this->coordinator)->getJson('/api/evaluations?type=midterm')
            ->assertOk()
            ->assertJsonPath('meta.counts', ['to_review' => 0, 'reviewed' => 0, 'not_submitted' => 2])
            ->json('data');
        $byName = collect($rows)->keyBy('student.name');
        $this->assertSame('draft', $byName['Juan Intern']['evaluation']['status']);
        $this->assertNull($byName['Juan Intern']['evaluation']['partner_mean']);
        $this->assertSame('not_started', $byName['Ana NotStarted']['evaluation']['status']);

        $draft = Evaluation::sole();
        $this->getJson("/api/evaluations/{$draft->id}")->assertOk()->assertJsonPath('data.ratings', [])->assertJsonPath('data.comment', null);
        $this->getJson('/api/evaluations?type=midterm&status=not_started')->assertJsonCount(1, 'data');
        $this->assertNotNull($other);
    }

    // Student

    public function test_students_only_see_reviewed_evaluations_without_notes(): void
    {
        Evaluation::create([
            'deployment_id' => $this->deployment->id, 'type' => 'midterm', 'ratings' => self::FULL, 'partner_mean' => 4.17,
            'comment' => 'Great start.', 'status' => EvaluationStatus::Reviewed, 'coordinator_rating' => 5,
            'coordinator_note' => 'Internal: verify DTR.', 'final_rating' => 4.42, 'reviewed_at' => now(),
            'return_note' => 'Old return note.',
        ]);
        Evaluation::create([
            'deployment_id' => $this->deployment->id, 'type' => 'final', 'ratings' => self::FULL, 'partner_mean' => 4.17,
            'status' => EvaluationStatus::Submitted,
        ]);

        $response = $this->as($this->student->user)->getJson('/api/student/evaluations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'midterm')
            ->assertJsonPath('data.0.final_rating', 4.42)
            ->assertJsonPath('data.0.final_descriptor', 'Very satisfactory')
            ->assertJsonPath('data.0.partner_descriptor', 'Very satisfactory')
            ->assertJsonPath('data.0.comment', 'Great start.')
            ->assertJsonPath('data.0.ratings.0', ['key' => 'quality', 'label' => 'Quality of work', 'value' => 5]);

        $this->assertArrayNotHasKey('coordinator_note', $response->json('data.0'));
        $this->assertArrayNotHasKey('return_note', $response->json('data.0'));
        $this->assertStringNotContainsString('Internal: verify DTR', $response->getContent());

        $other = $this->student($this->bsit, self::TAGUM);
        $this->as($other->user)->getJson('/api/student/evaluations')->assertOk()->assertJsonCount(0, 'data');
    }
}
