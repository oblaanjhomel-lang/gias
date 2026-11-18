<?php

namespace Tests\Unit;

use App\Services\CosineSimilarityService;
use App\Services\FuzzyLogicService;
use App\Services\WeightedSumService;
use Tests\TestCase;

/**
 * Hand-computed checks for the three recommenders (no database).
 *
 * Student {web 3, frontend 2, database 2, ui 2}; partner {web 2, frontend 2, database 2, ui 1,
 * technical writing 1}; distance 0.3 km; 4 open slots; default weights 40/25/20/15.
 */
class RecommendationAlgorithmsTest extends TestCase
{
    private const WEB = 1;

    private const FRONTEND = 2;

    private const DATABASE = 3;

    private const UI = 9;

    private const WRITING = 12;

    private const SETTINGS = [
        'weight_coverage' => 40,
        'weight_proficiency' => 25,
        'weight_proximity' => 20,
        'weight_slots' => 15,
        'proximity_zero_km' => 100,
        'slot_saturation' => 5,
    ];

    /** All 12 active skills (vectors run over every active skill). */
    private array $skills;

    private array $student = [self::WEB => 3, self::FRONTEND => 2, self::DATABASE => 2, self::UI => 2];

    private array $partner = [self::WEB => 2, self::FRONTEND => 2, self::DATABASE => 2, self::UI => 1, self::WRITING => 1];

    private WeightedSumService $wsm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skills = [
            1 => 'Web development (PHP/Laravel)', 2 => 'Frontend (Vue/React)', 3 => 'Database & SQL',
            4 => 'Networking', 5 => 'Technical support', 6 => 'Hardware troubleshooting', 7 => 'GIS & mapping',
            8 => 'Data analysis', 9 => 'Graphic & UI design', 10 => 'Mobile development',
            11 => 'Cybersecurity basics', 12 => 'Technical writing',
        ];
        $this->wsm = new WeightedSumService;
    }

    private function fuzzy(): FuzzyLogicService
    {
        return new FuzzyLogicService($this->wsm, config('gias.fuzzy'));
    }

    // Hand-computed case

    public function test_cosine_similarity(): void
    {
        $result = (new CosineSimilarityService)->score($this->skills, $this->student, $this->partner);

        // dot = 3·2 + 2·2 + 2·2 + 2·1 + 0·1 = 16; ‖s‖² = 21; ‖p‖² = 14
        $this->assertSame(16, $result['breakdown']['dot']);
        $this->assertEqualsWithDelta(sqrt(21), $result['breakdown']['norm_s'], 0.0001);
        $this->assertEqualsWithDelta(sqrt(14), $result['breakdown']['norm_p'], 0.0001);
        $this->assertEqualsWithDelta(16 / sqrt(21 * 14), $result['score'], 1e-9);
        $this->assertEqualsWithDelta(0.9332, $result['score'], 0.0001);

        $this->assertCount(4, $result['breakdown']['matched']);
        $this->assertSame([], $result['breakdown']['missing_required']); // writing is only preferred
        $this->assertSame(
            ['Technical writing', null],
            [end($result['breakdown']['needed'])['name'], end($result['breakdown']['needed'])['level']],
        );
    }

    public function test_weighted_sum(): void
    {
        $result = $this->wsm->score($this->skills, $this->student, $this->partner, 0.3, 4, self::SETTINGS);
        $criteria = array_column($result['breakdown']['criteria'], null, 'key');

        $this->assertEqualsWithDelta(0.875, $criteria['coverage']['value'], 0.0001);       // 7 / 8
        $this->assertEqualsWithDelta(5.3333 / 8, $criteria['proficiency']['value'], 0.0001); // ≈ 0.6667
        $this->assertEqualsWithDelta(0.997, $criteria['proximity']['value'], 0.0001);
        $this->assertEqualsWithDelta(0.8, $criteria['slots']['value'], 0.0001);
        $this->assertEqualsWithDelta(0.40, $criteria['coverage']['weight'], 0.0001);
        $this->assertEqualsWithDelta(0.35, $criteria['coverage']['contribution'], 0.0001);

        // 0.35 + 0.16667 + 0.1994 + 0.12
        $this->assertEqualsWithDelta(0.8361, $result['score'], 0.001);
    }

    public function test_fuzzy_logic(): void
    {
        $result = $this->fuzzy()->score($this->skills, $this->student, $this->partner, 0.3);
        $b = $result['breakdown'];

        $this->assertEqualsWithDelta(77.08, $b['fit'], 0.01);
        $this->assertEqualsWithDelta(0.5417, $b['fit_membership']['high'], 0.0001);
        $this->assertSame(0.0, $b['fit_membership']['medium']);
        $this->assertSame(0.0, $b['fit_membership']['low']);
        $this->assertEqualsWithDelta(0.99, $b['distance_membership']['near'], 0.0001);
        $this->assertSame(0.0, $b['distance_membership']['mid']);
        $this->assertSame(0.0, $b['distance_membership']['far']);

        $this->assertSame(
            [['fit' => 'high', 'distance' => 'near', 'then' => 'excellent', 'strength' => 0.5417]],
            $b['rules_fired'],
        );

        // Excellent tri(75,100,100) clipped at 0.5417, centroid over x = 0..100 = 920.01 / 10.14
        $this->assertEqualsWithDelta(90.73, $b['centroid'], 0.01);
        $this->assertEqualsWithDelta(0.90, $result['score'], 0.01);
        $this->assertFalse($b['distance_missing']);
    }

    // Edge cases

    public function test_a_zero_vector_gives_cosine_zero(): void
    {
        $cosine = new CosineSimilarityService;

        $this->assertSame(0.0, $cosine->score($this->skills, [], $this->partner)['score']);
        $this->assertSame(0.0, $cosine->score($this->skills, $this->student, [])['score']);
    }

    public function test_a_partner_that_needs_no_skills(): void
    {
        $result = $this->wsm->score($this->skills, $this->student, [], 0.0, 5, self::SETTINGS);
        $criteria = array_column($result['breakdown']['criteria'], 'value', 'key');

        $this->assertSame(0.0, $criteria['coverage']);
        $this->assertSame(0.0, $criteria['proficiency']);
        $this->assertEqualsWithDelta(0.20 + 0.15, $result['score'], 1e-9); // proximity 1 + slots 1

        $fuzzy = $this->fuzzy()->score($this->skills, $this->student, [], 0.0);
        $this->assertSame(0.0, $fuzzy['breakdown']['fit']);
        $this->assertSame('poor', $fuzzy['breakdown']['rules_fired'][0]['then']);
    }

    public function test_a_missing_distance(): void
    {
        $wsm = $this->wsm->score($this->skills, $this->student, $this->partner, null, 4, self::SETTINGS);
        $this->assertSame(0.0, array_column($wsm['breakdown']['criteria'], 'value', 'key')['proximity']);

        $fuzzy = $this->fuzzy()->score($this->skills, $this->student, $this->partner, null)['breakdown'];
        $this->assertTrue($fuzzy['distance_missing']);
        $this->assertEquals(120, $fuzzy['distance_km']);
        $this->assertSame(1.0, $fuzzy['distance_membership']['far']);
        $this->assertSame(
            [['fit' => 'high', 'distance' => 'far', 'then' => 'fair', 'strength' => 0.5417]],
            $fuzzy['rules_fired'],
        );
    }

    public function test_weights_are_normalised_when_they_do_not_sum_to_100(): void
    {
        $settings = ['weight_coverage' => 10, 'weight_proficiency' => 10, 'weight_proximity' => 0, 'weight_slots' => 0] + self::SETTINGS;

        $this->assertSame(
            ['coverage' => 0.5, 'proficiency' => 0.5, 'proximity' => 0.0, 'slots' => 0.0],
            $this->wsm->normalisedWeights($settings),
        );

        $result = $this->wsm->score($this->skills, $this->student, $this->partner, 0.3, 4, $settings);
        $this->assertEqualsWithDelta((0.875 + 5.3333 / 8) / 2, $result['score'], 0.0001);
    }

    public function test_inactive_skills_are_ignored(): void
    {
        $withoutWriting = $this->skills;
        unset($withoutWriting[self::WRITING]);

        // Technical writing deactivated: the partner's needs are web/frontend/database/ui only.
        $result = (new CosineSimilarityService)->score($withoutWriting, $this->student, $this->partner);
        $this->assertEqualsWithDelta(16 / sqrt(21 * 13), $result['score'], 1e-9);
    }

    public function test_triangle_shoulders(): void
    {
        $this->assertSame(1.0, FuzzyLogicService::tri(0, 0, 0, 30));    // left shoulder
        $this->assertSame(0.5, FuzzyLogicService::tri(15, 0, 0, 30));
        $this->assertSame(1.0, FuzzyLogicService::tri(150, 50, 120, 120)); // right shoulder beyond c
        $this->assertSame(0.5, FuzzyLogicService::tri(85, 50, 120, 120));
        $this->assertSame(1.0, FuzzyLogicService::tri(40, 15, 40, 65)); // peak
        $this->assertSame(0.0, FuzzyLogicService::tri(15, 15, 40, 65)); // foot
    }
}
