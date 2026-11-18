<?php

namespace App\Services;

/**
 * Cosine similarity between a student's skill levels and a partner's skill needs.
 * Pure and stateless: no database access.
 *
 * Vectors run over the ACTIVE skills: s[k] = student level 0–3, p[k] = partner importance 0–2.
 * score = (s · p) / (‖s‖ · ‖p‖), or 0 when either vector is all zeros.
 */
class CosineSimilarityService
{
    /**
     * @param  array<int, string>  $skills  active skills: id => name
     * @param  array<int, int>  $student  skill id => level (1–3)
     * @param  array<int, int>  $partner  skill id => importance (1 preferred, 2 required)
     * @return array{score: float, breakdown: array<string, mixed>}
     */
    public function score(array $skills, array $student, array $partner): array
    {
        $s = array_intersect_key($student, $skills);
        $p = array_intersect_key($partner, $skills);

        $dot = 0;
        foreach ($p as $skillId => $importance) {
            $dot += $importance * ($s[$skillId] ?? 0);
        }

        $normS = sqrt(array_sum(array_map(fn (int $v) => $v * $v, $s)));
        $normP = sqrt(array_sum(array_map(fn (int $v) => $v * $v, $p)));
        $score = $normS > 0 && $normP > 0 ? $dot / ($normS * $normP) : 0.0;

        $needed = [];
        foreach ($p as $skillId => $importance) {
            $needed[] = [
                'skill_id' => $skillId,
                'name' => $skills[$skillId],
                'importance' => $importance,
                'level' => isset($s[$skillId]) && $s[$skillId] > 0 ? $s[$skillId] : null,
            ];
        }
        usort($needed, fn (array $a, array $b) => [$b['importance'], $a['name']] <=> [$a['importance'], $b['name']]);

        return [
            'score' => $score,
            'breakdown' => [
                'dot' => $dot,
                'norm_s' => round($normS, 4),
                'norm_p' => round($normP, 4),
                'needed' => $needed,
                'matched' => array_values(array_filter($needed, fn (array $n) => $n['level'] !== null)),
                'missing_required' => array_values(array_filter(
                    $needed,
                    fn (array $n) => $n['level'] === null && $n['importance'] === 2,
                )),
            ],
        ];
    }
}
