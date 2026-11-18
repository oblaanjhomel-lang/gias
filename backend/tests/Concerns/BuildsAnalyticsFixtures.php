<?php

namespace Tests\Concerns;

use App\Enums\DeploymentStatus;
use App\Enums\Province;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\IndustryPartner;
use App\Models\RecommendationFeedback;
use App\Models\Student;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;

/**
 * A small dataset with known answers. Today is pinned to 17 Nov 2026 (AY 2026–2027,
 * 1st Semester: 1 Aug – 31 Dec 2026). BSIT needs 100 hours, BSAB 200.
 *
 *  Partners (active):  Alpha  Tagum (Davao del Norte), 4 slots, needs web (req) + database (pref)
 *                      Beta   Davao City,              6 slots, needs web (pref)
 *                      Gamma  Davao de Oro,            2 slots, needs networking (req), no interns
 *  Students:           s1 BSIT home Panabo  → Alpha, active,    120 h signed (past required), algo wsm
 *                      s2 BSIT home Tagum   → Beta,  active,     30 h signed,                 algo cos
 *                      s3 BSIT no home      → Alpha, completed, final reviewed 4.20,         manual
 *                      s4 BSIT              → Alpha, withdrawn (never counts)
 *                      s5 BSIT              → Beta,  completed in the previous term (Mar 2026)
 *                      b1 BSAB no home      → Beta,  active,    160/200 h signed,            algo fuzzy
 *  Skills held:        s1 web, s2 web, b1 database, s3 gis
 *  Feedback (term):    cos 3 and 5, wsm 4; one cos 1 in May 2026 (other term, ignored)
 */
trait BuildsAnalyticsFixtures
{
    use BuildsRecommendationFixtures;

    protected IndustryPartner $alpha;

    protected IndustryPartner $beta;

    protected IndustryPartner $gamma;

    /** @var array<string, Student> */
    protected array $s = [];

    /** @var array<string, Deployment> */
    protected array $d = [];

    protected function setUpAnalytics(): void
    {
        $this->travelTo('2026-11-17 02:00:00');
        $this->setUpFixtures();
        $this->bsit->update(['required_ojt_hours' => 100]);
        $this->bsab->update(['required_ojt_hours' => 200]);

        $this->alpha = $this->partner('Alpha Corp', self::TAGUM, 4, ['web' => 2, 'database' => 1]);
        $this->beta = $this->partner('Beta Inc', self::DAVAO, 6, ['web' => 1]);
        $this->beta->update(['province' => Province::DavaoCity, 'city' => 'Davao City']);
        $this->gamma = $this->partner('Gamma Coop', [7.6047, 125.9645], 2, ['networking' => 2]);
        $this->gamma->update(['province' => Province::DavaoDeOro, 'city' => 'Nabunturan']);

        $this->s['s1'] = $this->student($this->bsit, self::PANABO, ['web' => 3], name: 'Ana One');
        $this->s['s2'] = $this->student($this->bsit, self::TAGUM, ['web' => 2], name: 'Ben Two');
        $this->s['s3'] = $this->student($this->bsit, null, ['gis' => 2], name: 'Cara Three');
        $this->s['s4'] = $this->student($this->bsit, null, name: 'Dan Four');
        $this->s['s5'] = $this->student($this->bsit, null, name: 'Eve Five');
        $this->s['b1'] = $this->student($this->bsab, null, ['database' => 3], name: 'Fe Agri');

        $this->d['s1'] = $this->placement('s1', $this->alpha, DeploymentStatus::Active, 'wsm', [2, 1, 1]);
        $this->d['s2'] = $this->placement('s2', $this->beta, DeploymentStatus::Active, 'cos', [1, 3, 5]);
        $this->d['s3'] = $this->placement('s3', $this->alpha, DeploymentStatus::Completed, 'manual', [4, 2, 1]);
        $this->d['s4'] = $this->placement('s4', $this->alpha, DeploymentStatus::Withdrawn, 'cos', [1, 1, 1]);
        $this->d['s5'] = $this->placement('s5', $this->beta, DeploymentStatus::Completed, 'cos', [1, 1, 1], '2026-03-02');
        $this->d['b1'] = $this->placement('b1', $this->beta, DeploymentStatus::Active, 'fuzzy', [5, 2, 1]);

        $this->signed($this->d['s1'], [60, 60]);
        $this->signed($this->d['s2'], [30]);
        $this->signed($this->d['b1'], [80, 80]);

        Evaluation::unguarded(fn () => Evaluation::create([
            'deployment_id' => $this->d['s3']->id, 'type' => 'final', 'ratings' => ['quality' => 4, 'technical' => 3],
            'partner_mean' => 4.00, 'status' => 'reviewed', 'coordinator_rating' => 5, 'final_rating' => 4.20,
            'submitted_at' => now(), 'reviewed_at' => now(),
        ]));

        $this->feedback('s1', 'cos', 3, '2026-09-10');
        $this->feedback('s2', 'cos', 5, '2026-10-01');
        $this->feedback('s2', 'wsm', 4, '2026-10-02');
        $this->feedback('s1', 'cos', 1, '2026-05-01'); // previous term
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $ranks  cos, wsm, fuzzy
     */
    protected function placement(string $who, IndustryPartner $partner, DeploymentStatus $status, string $algorithm, array $ranks, string $start = '2026-09-01'): Deployment
    {
        return Deployment::create([
            'student_id' => $this->s[$who]->id,
            'industry_partner_id' => $partner->id,
            'status' => $status,
            'start_date' => $start,
            'algorithm_used' => $algorithm,
            'rank_cos' => $ranks[0],
            'rank_wsm' => $ranks[1],
            'rank_fuzzy' => $ranks[2],
        ]);
    }

    /**
     * @param  list<float>  $weeks  signed hours per week
     */
    protected function signed(Deployment $deployment, array $weeks): void
    {
        foreach ($weeks as $i => $hours) {
            [$start, $end] = WeeklyReportService::periodFor($deployment->start_date, $i + 1);
            WeeklyReport::create([
                'deployment_id' => $deployment->id, 'week_number' => $i + 1, 'period_start' => $start, 'period_end' => $end,
                'daily_hours' => ['mon' => $hours], 'total_hours' => $hours, 'tasks' => 'Routine tasks for the week.', 'status' => 'signed',
            ]);
        }
    }

    protected function feedback(string $who, string $algorithm, int $rating, string $date): void
    {
        $f = RecommendationFeedback::create([
            'student_id' => $this->s[$who]->id, 'algorithm' => $algorithm, 'rating' => $rating,
            'rated_by' => $this->coordinator->id, 'snapshot' => [],
        ]);
        $f->forceFill(['created_at' => "{$date} 10:00:00"])->save();
    }
}
