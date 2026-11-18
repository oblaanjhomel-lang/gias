<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsAnalyticsFixtures;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use BuildsAnalyticsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAnalytics();
    }

    private function report(string $type, array $query, ?User $as = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($as ?? $this->admin)->get("/api/reports/{$type}?".http_build_query($query));
    }

    /** @return list<list<string>> */
    private function rows(TestResponse $response): array
    {
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'UTF-8 BOM');

        return array_map(fn (string $line) => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", substr($body, 3)), fn ($l) => $l !== '')));
    }

    public function test_deployments_csv(): void
    {
        $this->s['s1']->user->update(['name' => 'Niño Peñaranda']);

        $response = $this->report('deployments', ['format' => 'csv'])->assertOk();

        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('gias-deployments-2026-2027-1.csv', $response->headers->get('Content-Disposition'));
        $rows = $this->rows($response);
        $this->assertSame('student', $rows[0][0]);
        $this->assertCount(1 + 5, $rows); // s1, s2, s3, s4 (withdrawn, listed), b1; s5 is last term
        $this->assertContains('Niño Peñaranda', array_column($rows, 0));
        $s3 = collect($rows)->firstWhere(0, 'Cara Three');
        $this->assertSame(['completed', '4.2', 'Very satisfactory'], [$s3[7], $s3[10], $s3[11]]);
    }

    public function test_coordinator_gets_only_their_programs_rows(): void
    {
        $rows = $this->rows($this->report('deployments', ['format' => 'csv', 'program_id' => $this->bsab->id], $this->coordinator)->assertOk());

        $this->assertCount(1 + 4, $rows);
        $this->assertSame(['BSIT'], array_values(array_unique(array_column(array_slice($rows, 1), 2))));

        $programs = $this->rows($this->report('programs', ['format' => 'csv'], $this->coordinator)->assertOk());
        $this->assertCount(2, $programs);
        $this->assertSame('BSIT', $programs[1][0]);
    }

    public function test_coordinator_cannot_export_the_partner_directory(): void
    {
        $this->report('partners', ['format' => 'csv'], $this->coordinator)->assertForbidden();
        $this->report('partners', ['format' => 'pdf'], $this->coordinator)->assertForbidden();
    }

    public function test_partners_csv_for_admin(): void
    {
        $rows = $this->rows($this->report('partners', ['format' => 'csv'])->assertOk());

        $this->assertCount(1 + 3, $rows);
        $alpha = collect($rows)->firstWhere(0, 'Alpha Corp');
        $this->assertSame(['4', '2', '50'], [$alpha[4], $alpha[5], $alpha[6]]); // s1 + s3 this term
        $this->assertEqualsWithDelta(self::TAGUM[0], (float) $alpha[8], 0.0001);
    }

    public function test_programs_and_algorithms_csv(): void
    {
        $programs = collect($this->rows($this->report('programs', ['format' => 'csv'])->assertOk()))->keyBy(0);
        $this->assertSame(['3', '4.2'], [$programs['BSIT'][6], $programs['BSIT'][10]]);

        $algorithms = $this->rows($this->report('algorithms', ['format' => 'csv'])->assertOk());
        $this->assertSame(['Cosine similarity', '1', '33.3', '66.7', '2.67', '4', '2'], $algorithms[1]);
        $placementHeader = array_search('student', array_column($algorithms, 0), true);
        $this->assertCount(4, array_slice($algorithms, $placementHeader + 1)); // s1, s2, s3 (manual), b1
    }

    public function test_pdf_downloads(): void
    {
        foreach (['deployments', 'partners', 'programs', 'algorithms'] as $type) {
            $response = $this->report($type, ['format' => 'pdf', 'term' => '2026-2027-1'])->assertOk();

            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString("gias-{$type}-2026-2027-1.pdf", $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_validation_and_access(): void
    {
        $this->report('deployments', [])->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->actingAs($this->admin)->getJson('/api/reports/deployments?format=xls')->assertUnprocessable();
        $this->actingAs($this->admin)->getJson('/api/reports/unknown?format=csv')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->alpha->user)->getJson('/api/reports/deployments?format=csv')->assertForbidden();
    }
}
