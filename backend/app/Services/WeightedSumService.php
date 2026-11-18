<?php

namespace App\Services;

/**
 * Weighted Sum Model. Pure and stateless: no database access.
 *
 *   coverage    = Σ_{p[k]>0} p[k]·[s[k]>0]  / Σ p[k]
 *   proficiency = Σ_{p[k]>0} p[k]·(s[k]/3) / Σ p[k]
 *   proximity   = max(0, 1 − distance_km / proximity_zero_km), 0 when distance is unknown
 *   slots       = min(1, open / slot_saturation)
 *   score       = Σ weight·criterion, weights normalised to sum to 1.
 * A partner that needs no skills gets coverage = proficiency = 0.
 */
class WeightedSumService
{
    public const CRITERIA = ['coverage', 'proficiency', 'proximity', 'slots'];

    /**
     * @param  array<int, string>  $skills  active skills: id => name
     * @param  array<int, int>  $student  skill id => level (1–3)
     * @param  array<int, int>  $partner  skill id => importance (1–2)
     * @param  array{weight_coverage: int, weight_proficiency: int, weight_proximity: int, weight_slots: int, proximity_zero_km: int, slot_saturation: int}  $settings
     * @return array{score: float, breakdown: array<string, mixed>}
     */
    public function score(array $skills, array $student, array $partner, ?float $distanceKm, int $open, array $settings): array
    {
        $s = array_intersect_key($student, $skills);
        $p = array_intersect_key($partner, $skills);

        $values = [
            'coverage' => $this->coverage($s, $p),
            'proficiency' => $this->proficiency($s, $p),
            'proximity' => $this->proximity($distanceKm, $settings['proximity_zero_km']),
            'slots' => $this->slots($open, $settings['slot_saturation']),
        ];
        $weights = $this->normalisedWeights($settings);

        $score = 0.0;
        $criteria = [];
        foreach (self::CRITERIA as $key) {
            $contribution = $weights[$key] * $values[$key];
            $score += $contribution;
            $criteria[] = [
                'key' => $key,
                'value' => round($values[$key], 4),
                'weight' => round($weights[$key], 4),
                'contribution' => round($contribution, 4),
            ];
        }

        return [
            'score' => $score,
            'breakdown' => [
                'criteria' => $criteria,
                'distance_km' => $distanceKm === null ? null : round($distanceKm, 1),
                'open' => $open,
            ],
        ];
    }

    /**
     * Share of the partner's (importance-weighted) needs the student has at all.
     *
     * @param  array<int, int>  $s
     * @param  array<int, int>  $p
     */
    public function coverage(array $s, array $p): float
    {
        $total = array_sum($p);
        if ($total <= 0) {
            return 0.0;
        }

        $covered = 0;
        foreach ($p as $skillId => $importance) {
            if (($s[$skillId] ?? 0) > 0) {
                $covered += $importance;
            }
        }

        return $covered / $total;
    }

    /**
     * Importance-weighted average of the student's level (÷ 3) on the needed skills.
     *
     * @param  array<int, int>  $s
     * @param  array<int, int>  $p
     */
    public function proficiency(array $s, array $p): float
    {
        $total = array_sum($p);
        if ($total <= 0) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($p as $skillId => $importance) {
            $sum += $importance * (($s[$skillId] ?? 0) / 3);
        }

        return $sum / $total;
    }

    public function proximity(?float $distanceKm, int|float $zeroKm): float
    {
        if ($distanceKm === null || $zeroKm <= 0) {
            return 0.0;
        }

        return max(0.0, 1 - $distanceKm / $zeroKm);
    }

    public function slots(int $open, int $saturation): float
    {
        if ($saturation <= 0) {
            return $open > 0 ? 1.0 : 0.0;
        }

        return min(1.0, max(0, $open) / $saturation);
    }

    /**
     * Each weight ÷ the sum of the four (all zeros → all zeros; validation prevents that).
     *
     * @param  array<string, int>  $settings
     * @return array{coverage: float, proficiency: float, proximity: float, slots: float}
     */
    public function normalisedWeights(array $settings): array
    {
        $raw = [
            'coverage' => max(0, (int) $settings['weight_coverage']),
            'proficiency' => max(0, (int) $settings['weight_proficiency']),
            'proximity' => max(0, (int) $settings['weight_proximity']),
            'slots' => max(0, (int) $settings['weight_slots']),
        ];
        $sum = array_sum($raw);

        return array_map(fn (int $w) => $sum > 0 ? (float) $w / $sum : 0.0, $raw);
    }
}
