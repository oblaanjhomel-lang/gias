<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\IndustryPartner;
use App\Models\Program;
use App\Models\RecommendationFeedback;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\DeploymentMonitoringService;
use App\Services\EvaluationService;
use App\Services\WeeklyReportService;
use App\Support\AcademicTerm;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_run_outside_local(): void
    {
        $this->assertFalse(app()->environment('local'));

        try {
            $this->seed(DemoSeeder::class);
            $this->fail('DemoSeeder ran outside the local environment.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('APP_ENV=local', $e->getMessage());
        }

        $this->assertSame(0, User::count());
    }

    public function test_it_seeds_consistent_data_and_can_run_twice(): void
    {
        $this->app['env'] = 'local';
        $this->travelTo('2026-11-17 02:00:00'); // demo reports are relative to today

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class); // idempotent

        // One demo coordinator per program (ProgramSeeder's 20 DNSC programs).
        $this->assertSame(count(ProgramSeeder::PROGRAMS), Program::count());
        $this->assertSame(Program::count(), User::where('role', UserRole::Coordinator)->count());
        $this->assertSame(19, IndustryPartner::count()); // 16 active located + 2 pending + 1 unlocated
        $this->assertSame(1, IndustryPartner::whereNull('location')->count());
        $this->assertSame(60, User::where('role', UserRole::Student)->where('status', 'active')->count());
        $this->assertSame(243, User::where('email', 'like', 'alumni%')->where('status', 'inactive')->count());
        $this->assertSame(44, Deployment::where('status', DeploymentStatus::Active)->count());
        $this->assertSame(1 + 243, Deployment::where('status', DeploymentStatus::Completed)->count());

        // Weekly reports (seeded relative to "today", pinned above).
        $this->assertSame(4, WeeklyReport::where('status', 'submitted')->count());
        $this->assertSame(2, WeeklyReport::where('status', 'returned')->count());
        $this->assertSame(0, WeeklyReport::where('status', 'draft')->count());
        $summary = app(DeploymentMonitoringService::class)->summary(User::factory()->role(UserRole::Admin)->create());
        $this->assertSame(3, $summary['overdue']);
        $this->assertSame(4, $summary['reports_awaiting_signature']);
        $completed = Deployment::where('status', DeploymentStatus::Completed)->where('start_date', '2026-06-02')->whereHas('student.user', fn ($q) => $q->where('email', 'student01@demo.gias.test'))->sole();

        // Evaluations (Module 7)
        $finals = Evaluation::where('type', 'final')->get();
        $this->assertSame(3, $finals->where('status', EvaluationStatus::Submitted)->count());
        $this->assertSame(2, $finals->where('status', EvaluationStatus::Draft)->count());
        $this->assertSame(1, $finals->where('status', EvaluationStatus::Returned)->count());
        $completedFinal = $finals->firstWhere('deployment_id', $completed->id);
        $this->assertSame(EvaluationStatus::Reviewed, $completedFinal->status);
        $this->assertNotNull($completedFinal->coordinator_rating);
        $this->assertGreaterThan(30, Evaluation::where('type', 'midterm')->where('status', 'reviewed')->count());
        foreach ($finals as $final) {
            $this->assertTrue(app(EvaluationService::class)->openState($final->deployment, EvaluationType::Final)['open'] || $final->deployment->status === DeploymentStatus::Completed);
        }
        $this->assertGreaterThanOrEqual(486, (float) $completed->weeklyReports()->where('status', 'signed')->sum('total_hours'));
        foreach (WeeklyReport::with('deployment')->get() as $report) {
            [$start, $end] = WeeklyReportService::periodFor($report->deployment->start_date, $report->week_number);
            $this->assertSame([$start->toDateString(), $end->toDateString()], [$report->period_start->toDateString(), $report->period_end->toDateString()]);
            $this->assertLessThanOrEqual(60, (float) $report->total_hours);
        }

        // Analytics history (Module 9): growing placements per term, rank data, feedback.
        $trend = [];
        foreach (array_reverse(AcademicTerm::list(5, AcademicTerm::forDate('2026-09-01'))) as $term) {
            $trend[] = Deployment::where('status', '<>', 'withdrawn')->whereBetween('start_date', [$term->start->toDateString(), $term->end->toDateString()])->count();
        }
        $this->assertSame([48, 55, 63, 78, 44], $trend);
        $this->assertSame(0, Deployment::whereNull('algorithm_used')->count());
        $this->assertSame(0, Deployment::whereNull('rank_cos')->count());
        $this->assertSame(25, RecommendationFeedback::count());
        $this->assertSame(['cos', 'fuzzy', 'wsm'], RecommendationFeedback::distinct()->orderBy('algorithm')->pluck('algorithm')->all());
        // Plain joins: nested whereHas (correlated EXISTS) can get a pathological plan on
        // freshly seeded, un-analyzed tables inside the test transaction.
        $this->assertSame(243, Evaluation::query()
            ->join('deployments', 'deployments.id', '=', 'evaluations.deployment_id')
            ->join('students', 'students.id', '=', 'deployments.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('evaluations.type', 'final')
            ->where('evaluations.status', 'reviewed')
            ->where('users.email', 'like', 'alumni%')
            ->count());

        $partners = IndustryPartner::withCount(['deployments as filled' => fn ($q) => $q->where('status', 'active')])->get();
        foreach ($partners as $partner) {
            $this->assertLessThanOrEqual($partner->intern_slots, $partner->filled, "{$partner->company_name} is over capacity");
        }
        $this->assertTrue($partners->contains(fn ($p) => $p->filled > 0 && $p->filled === $p->intern_slots), 'No full partner');
        $this->assertGreaterThanOrEqual(3, $partners->filter(fn ($p) => in_array($p->intern_slots - $p->filled, [1, 2], true))->count());
    }
}
