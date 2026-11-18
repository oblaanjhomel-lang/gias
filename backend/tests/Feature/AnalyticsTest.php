<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Models\Program;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAnalyticsFixtures;
use Tests\TestCase;

/**
 * Known answers for the dataset in BuildsAnalyticsFixtures.
 */
class AnalyticsTest extends TestCase
{
    use BuildsAnalyticsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAnalytics();
    }

    /** @return array<string, mixed> */
    private function overview(array $query = [], $as = null): array
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($as ?? $this->admin)
            ->getJson('/api/analytics/overview?'.http_build_query($query))
            ->assertOk()
            ->json('data');
    }

    /** Great-circle distance in km (sphere); PostGIS uses the spheroid, within ~0.3%. */
    private function km(array $a, array $b): float
    {
        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [$a[0], $a[1], $b[0], $b[1]]);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 2 * 6371.0088 * asin(sqrt($h));
    }

    public function test_kpis(): void
    {
        $kpis = $this->overview()['kpis'];

        // 4 of 6 active students placed this term (s4 withdrawn, s5 placed last term).
        $this->assertSame(['rate' => 66.7, 'numerator' => 4, 'denominator' => 6], $kpis['placement_rate']);
        $this->assertSame(3, $kpis['active_partners']);
        $this->assertSame(['rate' => 33.3, 'filled' => 4, 'slots' => 12], $kpis['partner_utilization']);
        // completed s3 ÷ (s3 + s1, active past its 100 h)
        $this->assertSame(['rate' => 50, 'completed' => 1, 'active_past_required' => 1], $kpis['completion_rate']);
        $this->assertSame(['value' => 4.2, 'descriptor' => 'Very satisfactory'], $kpis['avg_final_rating']);
        $this->assertSame(4, $kpis['placements']);
    }

    public function test_average_distance_uses_the_two_pairs_with_both_points(): void
    {
        $expected = ($this->km(self::PANABO, self::TAGUM) + $this->km(self::TAGUM, self::DAVAO)) / 2;

        $this->assertEqualsWithDelta($expected, $this->overview()['kpis']['avg_distance_km'], 0.5);
    }

    public function test_by_province_lists_all_six_with_underserved_flags(): void
    {
        $rows = collect($this->overview()['by_province'])->keyBy('province');

        $this->assertCount(6, $rows);
        $this->assertSame(['province' => 'Davao del Norte', 'interns' => 2, 'partners' => 1, 'open_slots' => 3, 'underserved' => false], $rows['Davao del Norte']);
        $this->assertSame(['province' => 'Davao City', 'interns' => 2, 'partners' => 1, 'open_slots' => 4, 'underserved' => false], $rows['Davao City']);
        $this->assertSame(['province' => 'Davao de Oro', 'interns' => 0, 'partners' => 1, 'open_slots' => 2, 'underserved' => true], $rows['Davao de Oro']);
        $this->assertSame(0, $rows['Davao Occidental']['partners']);
        $this->assertTrue($rows['Davao Occidental']['underserved']);
    }

    public function test_placements_trend_covers_five_terms_oldest_first(): void
    {
        $trend = $this->overview()['placements_trend'];

        $this->assertSame(['2025-2026-1', '2025-2026-2', '2025-2026-3', '2026-2027-1'], array_slice(array_column($trend, 'key'), 1));
        $this->assertSame([0, 0, 1, 0, 4], array_column($trend, 'placements')); // s5 in the 2nd semester
    }

    public function test_hours_buckets(): void
    {
        // s2 30/100 → 25–50; b1 160/200 → 75–<100; s1 120/100 → 100
        $this->assertSame([0, 1, 0, 1, 1], array_column($this->overview()['hours_distribution'], 'interns'));
    }

    public function test_workload_labels(): void
    {
        $rows = collect($this->overview()['workload'])->keyBy('program.code');

        $this->assertSame(['coordinator' => $this->coordinator->name, 'interns' => 3, 'partners_used' => 2, 'load' => 'light'], collect($rows['BSIT'])->except('program')->all());
        $this->assertSame('no_coordinator', $rows['BSAB']['load']);
        $this->assertNull($rows['BSAB']['coordinator']);

        $this->assertSame('heavy', AnalyticsService::load('X', 21));
        $this->assertSame('balanced', AnalyticsService::load('X', 20));
        $this->assertSame('balanced', AnalyticsService::load('X', 8));
        $this->assertSame('light', AnalyticsService::load('X', 7));
        $this->assertSame('no_coordinator', AnalyticsService::load(null, 30));
    }

    public function test_top_partners_and_skills_gap_ordering(): void
    {
        $data = $this->overview();

        $this->assertSame(['Alpha Corp', 'Beta Inc'], array_column($data['top_partners'], 'company'));
        $this->assertSame(4.2, $data['top_partners'][0]['avg_final_rating']);

        $gap = $data['skills_gap'];
        $this->assertSame(['skill' => 'Networking', 'partners_need' => 1, 'students_have' => 0, 'gap' => 1], $gap[0]);
        $this->assertSame(['skill' => 'GIS & mapping', 'partners_need' => 0, 'students_have' => 1, 'gap' => -1], end($gap));
        $web = collect($gap)->firstWhere('skill', 'Web development (PHP/Laravel)');
        $this->assertSame([2, 2, 0], [$web['partners_need'], $web['students_have'], $web['gap']]);
        $gaps = array_column($gap, 'gap');
        $sorted = $gaps;
        rsort($sorted);
        $this->assertSame($sorted, $gaps);
    }

    public function test_evaluation_uses_the_final_summary_in_scope(): void
    {
        $evaluation = $this->overview()['evaluation'];

        $this->assertSame(1, $evaluation['evaluated']);
        $this->assertSame('technical', $evaluation['lowest_criterion']['key']);
    }

    public function test_algorithm_comparison(): void
    {
        $comparison = $this->overview()['algorithm_comparison'];
        $by = collect($comparison['algorithms'])->keyBy('algorithm');

        $this->assertSame(3, $comparison['total_with_rank_data']);
        $this->assertSame(1, $comparison['manual_placements']);

        // Ranks of the chosen partner (s1, s2, b1): cos 2,1,5 · wsm 1,3,2 · fuzzy 1,5,1
        $this->assertSame([1, 33.3, 66.7, 2.67, 4, 2], [$by['cos']['placements_using'], $by['cos']['top1_agreement'], $by['cos']['top3_agreement'], $by['cos']['mean_rank_of_chosen'], $by['cos']['avg_feedback_rating'], $by['cos']['feedback_n']]);
        $this->assertSame([1, 33.3, 100, 2, 4, 1], [$by['wsm']['placements_using'], $by['wsm']['top1_agreement'], $by['wsm']['top3_agreement'], $by['wsm']['mean_rank_of_chosen'], $by['wsm']['avg_feedback_rating'], $by['wsm']['feedback_n']]);
        $this->assertSame([1, 66.7, 66.7, 2.33, null, 0], [$by['fuzzy']['placements_using'], $by['fuzzy']['top1_agreement'], $by['fuzzy']['top3_agreement'], $by['fuzzy']['mean_rank_of_chosen'], $by['fuzzy']['avg_feedback_rating'], $by['fuzzy']['feedback_n']]);
    }

    public function test_a_coordinator_cannot_override_the_program(): void
    {
        $data = $this->overview(['program_id' => $this->bsab->id], $this->coordinator);

        $this->assertSame($this->bsit->id, $data['scope']['program_id']);
        $this->assertSame(3, $data['kpis']['placements']);
        $this->assertNull($data['workload']);
        $this->assertNotEmpty($data['top_partners']);
    }

    public function test_admin_program_and_province_filters(): void
    {
        $this->assertSame(1, $this->overview(['program_id' => $this->bsab->id])['kpis']['placements']);

        $city = $this->overview(['province' => 'Davao City']);
        $this->assertSame(2, $city['kpis']['placements']);
        $this->assertSame(1, $city['kpis']['active_partners']);
        $this->assertCount(6, $city['by_province']);
    }

    public function test_past_terms_and_validation(): void
    {
        $past = $this->overview(['term' => '2025-2026-2']);
        $this->assertSame(1, $past['kpis']['placements']);
        $this->assertSame('AY 2025–2026 · 2nd Semester', $past['scope']['term']['label']);

        $this->actingAs($this->admin)->getJson('/api/analytics/overview?term=2026-2028-1')->assertUnprocessable();
        $this->actingAs($this->admin)->getJson('/api/analytics/overview?province=Cebu')->assertUnprocessable();
    }

    public function test_students_and_partners_cannot_see_analytics(): void
    {
        $this->actingAs($this->s['s1']->user)->getJson('/api/analytics/overview')->assertForbidden();
    }

    public function test_results_are_cached_for_five_minutes(): void
    {
        $this->overview();

        DB::enableQueryLog();
        $this->overview();
        $this->assertCount(0, DB::getQueryLog(), 'second call within 5 minutes should hit the cache');

        $this->travel(6)->minutes();
        DB::flushQueryLog();
        $this->overview();
        $this->assertGreaterThan(5, count(DB::getQueryLog()), 'expired after 5 minutes');
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        DB::enableQueryLog();
        $this->overview();
        $before = count(DB::getQueryLog());

        // Much more data: programs, partners, students, deployments.
        for ($i = 0; $i < 6; $i++) {
            $program = Program::create(['code' => "P{$i}", 'name' => "Program {$i}"]);
            $partner = $this->partner("Extra {$i}", self::TAGUM, 3, ['web' => 1]);
            $this->deploy($this->student($program, self::PANABO, ['web' => 2]), $partner, DeploymentStatus::Active);
        }
        cache()->flush();

        DB::flushQueryLog();
        $this->overview();
        $this->assertSame($before, count(DB::getQueryLog()));
    }
}
