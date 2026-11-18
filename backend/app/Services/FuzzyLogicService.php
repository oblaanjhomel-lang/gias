<?php

namespace App\Services;

/**
 * Mamdani fuzzy recommender (config('gias.fuzzy')). Pure and stateless: no database access.
 *
 * Inputs: fit = (coverage + proficiency) / 2 × 100 (WeightedSumService's criteria) and
 * distance_km (missing → treated as far). Rule strength = min of the antecedents; each output
 * set is clipped at its strongest rule; sets are aggregated with max; the centroid is sampled
 * at the integers 0..100 and divided by 100.
 */
class FuzzyLogicService
{
    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param  array<string, mixed>|null  $config  defaults to config('gias.fuzzy')
     */
    public function __construct(private readonly WeightedSumService $wsm, ?array $config = null)
    {
        $this->config = $config ?? config('gias.fuzzy');
    }

    /**
     * Triangular membership. a == b is a left shoulder (1 up to b); b == c a right shoulder (1 from b).
     */
    public static function tri(float $x, float $a, float $b, float $c): float
    {
        if ($a == $b) {
            if ($x <= $b) {
                return 1.0;
            }

            return $x >= $c ? 0.0 : ($c - $x) / ($c - $b);
        }

        if ($b == $c) {
            if ($x >= $b) {
                return 1.0;
            }

            return $x <= $a ? 0.0 : ($x - $a) / ($b - $a);
        }

        if ($x <= $a || $x >= $c) {
            return 0.0;
        }

        return $x <= $b ? ($x - $a) / ($b - $a) : ($c - $x) / ($c - $b);
    }

    /**
     * @param  array<int, string>  $skills  active skills: id => name
     * @param  array<int, int>  $student  skill id => level (1–3)
     * @param  array<int, int>  $partner  skill id => importance (1–2)
     * @return array{score: float, breakdown: array<string, mixed>}
     */
    public function score(array $skills, array $student, array $partner, ?float $distanceKm): array
    {
        $s = array_intersect_key($student, $skills);
        $p = array_intersect_key($partner, $skills);

        $fit = ($this->wsm->coverage($s, $p) + $this->wsm->proficiency($s, $p)) / 2 * 100;
        $distanceMissing = $distanceKm === null;
        $distance = $distanceMissing ? (float) $this->config['missing_distance_km'] : $distanceKm;

        $fitDegrees = $this->fuzzify($this->config['inputs']['fit'], $fit);
        $distanceDegrees = $this->fuzzify($this->config['inputs']['distance_km'], $distance);

        // Rule evaluation: strength = min(antecedents); each output set keeps its strongest rule.
        $clip = array_fill_keys(array_keys($this->config['output']), 0.0);
        $fired = [];
        foreach ($this->config['rules'] as $rule) {
            $strength = $rule['distance'] === 'any'
                ? $fitDegrees[$rule['fit']]
                : min($fitDegrees[$rule['fit']], $distanceDegrees[$rule['distance']]);

            $clip[$rule['then']] = max($clip[$rule['then']], $strength);

            if ($strength > 0) {
                $fired[] = [
                    'fit' => $rule['fit'],
                    'distance' => $rule['distance'],
                    'then' => $rule['then'],
                    'strength' => round($strength, 4),
                ];
            }
        }

        // Aggregate (max of clipped sets) and take the centroid at x = 0..100.
        $numerator = 0.0;
        $denominator = 0.0;
        for ($x = 0; $x <= 100; $x++) {
            $mu = 0.0;
            foreach ($this->config['output'] as $term => [$a, $b, $c]) {
                $mu = max($mu, min($clip[$term], self::tri($x, $a, $b, $c)));
            }
            $numerator += $x * $mu;
            $denominator += $mu;
        }
        $centroid = $denominator > 0 ? $numerator / $denominator : 0.0;

        return [
            'score' => $centroid / 100,
            'breakdown' => [
                'fit' => round($fit, 2),
                'fit_membership' => array_map(fn (float $v) => round($v, 4), $fitDegrees),
                'distance_km' => round($distance, 1),
                'distance_missing' => $distanceMissing,
                'distance_membership' => array_map(fn (float $v) => round($v, 4), $distanceDegrees),
                'rules_fired' => $fired,
                'centroid' => round($centroid, 2),
            ],
        ];
    }

    /**
     * @param  array<string, array{0: int|float, 1: int|float, 2: int|float}>  $terms
     * @return array<string, float>
     */
    private function fuzzify(array $terms, float $x): array
    {
        return array_map(fn (array $t) => self::tri($x, $t[0], $t[1], $t[2]), $terms);
    }
}
