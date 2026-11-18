<?php

namespace Tests\Feature;

use App\Enums\EvaluationStatus;
use App\Models\Deployment;
use App\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsRecommendationFixtures;
use Tests\TestCase;

class EvaluationReportTest extends TestCase
{
    use BuildsRecommendationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    private function evaluation(Deployment $d, array $ratings, EvaluationStatus $status, ?float $final = null, string $type = 'final'): Evaluation
    {
        return Evaluation::create([
            'deployment_id' => $d->id, 'type' => $type, 'ratings' => $ratings,
            'partner_mean' => round(array_sum($ratings) / max(1, count($ratings)), 2),
            'status' => $status, 'final_rating' => $final, 'coordinator_rating' => $final ? 5 : null,
            'reviewed_at' => $status === EvaluationStatus::Reviewed ? now() : null,
        ]);
    }

    private function ratings(int $quality, int $technical, int $attendance, int $initiative, int $communication, int $teamwork): array
    {
        return compact('quality', 'technical', 'attendance', 'initiative', 'communication', 'teamwork');
    }

    public function test_summary_averages_lowest_criterion_and_programs(): void
    {
        $host = $this->partner('Host Co', self::TAGUM, 10);
        $a = $this->deploy($this->student($this->bsit, self::TAGUM), $host);
        $b = $this->deploy($this->student($this->bsit, self::TAGUM), $host);
        $c = $this->deploy($this->student($this->bsab, self::TAGUM), $host);
        $this->deploy($this->student($this->bsab, self::TAGUM), $host); // no evaluation yet

        $this->evaluation($a, $this->ratings(5, 4, 5, 4, 2, 5), EvaluationStatus::Reviewed, 4.25); // mean 4.17
        $this->evaluation($b, $this->ratings(3, 4, 5, 4, 2, 4), EvaluationStatus::Submitted);        // mean 3.67
        $this->evaluation($c, $this->ratings(4, 4, 4, 4, 4, 4), EvaluationStatus::Submitted);        // mean 4.00
        $this->evaluation($a, $this->ratings(1, 1, 1, 1, 1, 1), EvaluationStatus::Draft, type: 'midterm'); // excluded

        $this->actingAs($this->admin, 'web')->getJson('/api/evaluations/summary?type=final')
            ->assertOk()
            ->assertJsonPath('data.counts', ['draft' => 0, 'submitted' => 2, 'returned' => 0, 'reviewed' => 1])
            ->assertJsonPath('data.evaluations_submitted', 3)
            ->assertJsonPath('data.awaiting_review', 2)
            ->assertJsonPath('data.average_partner_mean', 3.95) // (4.17 + 3.67 + 4.00) / 3
            ->assertJsonPath('data.average_descriptor', 'Very satisfactory')
            ->assertJsonPath('data.criteria.0', ['key' => 'quality', 'label' => 'Quality of work', 'average' => 4])
            ->assertJsonPath('data.criteria.4', ['key' => 'communication', 'label' => 'Communication', 'average' => 2.67])
            ->assertJsonPath('data.lowest_criterion.key', 'communication')
            ->assertJsonPath('data.programs', [
                ['program' => ['code' => 'BSAB', 'name' => 'BS Agribusiness'], 'evaluated' => 1, 'total_active_or_completed' => 2,
                    'avg_partner_mean' => 4, 'avg_final' => null, 'descriptor' => 'Very satisfactory'],
                ['program' => ['code' => 'BSIT', 'name' => 'BS Information Technology'], 'evaluated' => 2, 'total_active_or_completed' => 2,
                    'avg_partner_mean' => 3.92, 'avg_final' => 4.25, 'descriptor' => 'Very satisfactory'],
            ]);

        // Without a type both evaluations count (the midterm draft still doesn't).
        $this->getJson('/api/evaluations/summary')->assertJsonPath('data.counts.draft', 1)->assertJsonPath('data.evaluations_submitted', 3);

        // Coordinator: own program only.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')->getJson('/api/evaluations/summary?type=final')
            ->assertJsonPath('data.evaluations_submitted', 2)
            ->assertJsonCount(1, 'data.programs')
            ->assertJsonPath('data.programs.0.program.code', 'BSIT');
    }

    public function test_csv_export(): void
    {
        $host = $this->partner('Host Co', self::TAGUM, 10);
        $student = $this->student($this->bsit, self::TAGUM, name: 'Juan Dela Cruz');
        $student->update(['student_number' => '2023-00042']);
        $this->evaluation($this->deploy($student, $host), $this->ratings(5, 4, 4, 5, 3, 4), EvaluationStatus::Reviewed, 4.42);

        $response = $this->actingAs($this->admin, 'web')->get('/api/admin/evaluations/export?type=final')->assertOk();
        $lines = array_map('str_getcsv', explode("\n", trim(ltrim($response->streamedContent(), "\xEF\xBB\xBF"))));

        $this->assertSame([
            'student', 'student_number', 'program', 'partner', 'type', 'Quality of work', 'Technical competence',
            'Attendance & punctuality', 'Initiative', 'Communication', 'Teamwork & conduct',
            'partner_mean', 'coordinator_rating', 'final_rating', 'status', 'reviewed_at',
        ], $lines[0]);
        $this->assertSame([
            'Juan Dela Cruz', '2023-00042', 'BSIT', 'Host Co', 'final', '5', '4', '4', '5', '3', '4',
            '4.17', '5', '4.42', 'reviewed', now()->toDateString(),
        ], $lines[1]);

        $this->getJson('/api/admin/evaluations/export?type=other')->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->coordinator, 'web')->get('/api/admin/evaluations/export?type=final')->assertForbidden();
    }
}
