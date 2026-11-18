<?php

namespace App\Services;

use App\Enums\EvaluationType;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AcademicTerm;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Analytics (admin, coordinator). Every figure is a SQL aggregate.
 *
 * Scope:
 *  - term: a deployment belongs to the term of its start_date; withdrawn ones don't count.
 *  - program: admin may pick one; a coordinator is always held to their own program.
 *  - province: the HOST partner's province for deployment and partner figures, the
 *    student's home province for student-population figures (placement rate, skills held).
 *    "by_province" always lists all six provinces.
 *  - Partner capacity (active partners, utilization, open slots) is shared across programs,
 *    so it isn't narrowed by program.
 */
class AnalyticsService
{
    public const CACHE_SECONDS = 300;

    public const ALGORITHMS = ['cos' => 'Cosine similarity', 'wsm' => 'Weighted sum (WSM)', 'fuzzy' => 'Fuzzy logic'];

    public const HOUR_BUCKETS = ['0–25%', '25–50%', '50–75%', '75–<100%', '100%'];

    /** Signed hours of a deployment row aliased "d". */
    private const SIGNED_SQL = "(select coalesce(sum(w.total_hours), 0) from weekly_reports w where w.deployment_id = d.id and w.status = 'signed')";

    private const REQUIRED_SQL = 'coalesce(p.required_ojt_hours, 486)';

    /** Open slots of a partner row aliased "ip" (never negative). */
    private const OPEN_SLOTS_SQL = "greatest(ip.intern_slots - (select count(*) from deployments x where x.industry_partner_id = ip.id and x.status = 'active'), 0)";

    public function __construct(private readonly EvaluationReportService $evaluationReports) {}

    /**
     * @param  array{term?: string|null, program_id?: int|string|null, province?: string|null}  $filters
     * @return array{term: AcademicTerm, program_id: int|null, province: string|null, admin: bool}
     */
    public function scope(User $viewer, array $filters): array
    {
        $admin = $viewer->role === UserRole::Admin;

        return [
            'term' => ! empty($filters['term']) ? AcademicTerm::fromKey($filters['term']) : AcademicTerm::current(),
            // A coordinator without a program sees nothing (program 0 matches no rows).
            'program_id' => $admin
                ? (isset($filters['program_id']) && $filters['program_id'] !== '' ? (int) $filters['program_id'] : null)
                : (int) ($viewer->program_id ?? 0),
            'province' => ($filters['province'] ?? null) ?: null,
            'admin' => $admin,
        ];
    }

    /**
     * Cached for 5 minutes per role + scope + filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(User $viewer, array $filters): array
    {
        $scope = $this->scope($viewer, $filters);
        $key = 'analytics:overview:'.md5(json_encode([
            $scope['admin'] ? 'admin' : 'coordinator', $scope['term']->key(), $scope['program_id'], $scope['province'],
        ]));

        return Cache::remember($key, self::CACHE_SECONDS, fn () => $this->compute($viewer, $scope));
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function compute(User $viewer, array $scope): array
    {
        return [
            'scope' => [
                'term' => $scope['term']->toArray(),
                'program_id' => $scope['program_id'],
                'province' => $scope['province'],
            ],
            'kpis' => $this->kpis($scope),
            'by_province' => $this->byProvince($scope),
            'placements_trend' => $this->placementsTrend($scope),
            'hours_distribution' => $this->hoursDistribution($scope),
            'workload' => $scope['admin'] ? $this->workload($scope) : null,
            'top_partners' => $this->topPartners($scope),
            'skills_gap' => $this->skillsGap($scope),
            'evaluation' => $this->evaluation($viewer, $scope),
            'algorithm_comparison' => $this->algorithmComparison($scope),
        ];
    }

    // ---- Building blocks (also used by ReportService) ----

    /**
     * The term's deployments in scope: d, s (student), su (student user), ip (partner), p (program).
     *
     * @param  array<string, mixed>  $scope
     */
    public function deployments(array $scope, bool $withTerm = true, bool $withProvince = true, bool $withWithdrawn = false): QueryBuilder
    {
        return DB::table('deployments as d')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->join('users as su', 'su.id', '=', 's.user_id')
            ->join('industry_partners as ip', 'ip.id', '=', 'd.industry_partner_id')
            ->leftJoin('programs as p', 'p.id', '=', 'su.program_id')
            ->when(! $withWithdrawn, fn (QueryBuilder $q) => $q->where('d.status', '<>', 'withdrawn'))
            ->when($withTerm, fn (QueryBuilder $q) => $q->whereBetween('d.start_date', $this->termDates($scope['term'])))
            ->when($scope['program_id'] !== null, fn (QueryBuilder $q) => $q->where('su.program_id', $scope['program_id']))
            ->when($withProvince && $scope['province'], fn (QueryBuilder $q) => $q->where('ip.province', $scope['province']));
    }

    /**
     * Active (approved) partners: ip + pu (partner user).
     *
     * @param  array<string, mixed>  $scope
     */
    public function activePartners(array $scope, bool $withProvince = true): QueryBuilder
    {
        return DB::table('industry_partners as ip')
            ->join('users as pu', 'pu.id', '=', 'ip.user_id')
            ->where('pu.status', UserStatus::Active->value)
            ->when($withProvince && $scope['province'], fn (QueryBuilder $q) => $q->where('ip.province', $scope['province']));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function termDates(AcademicTerm $term): array
    {
        return [$term->start->toDateString(), $term->end->toDateString()];
    }

    public static function signedSql(): string
    {
        return self::SIGNED_SQL;
    }

    // ---- Sections ----

    /**
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function kpis(array $scope): array
    {
        $students = $this->studentPopulation($scope)->selectRaw('count(*) filter (where t.placed) as placed, count(*) as total')->first();

        $partners = $this->activePartners($scope)->selectRaw('count(*) as partners, coalesce(sum(ip.intern_slots), 0) as slots')->first();
        // Capacity is shared by all programs: filled counts every program's interns.
        $filled = $this->deployments(['program_id' => null] + $scope)
            ->join('users as pu', 'pu.id', '=', 'ip.user_id')
            ->where('pu.status', UserStatus::Active->value)
            ->count();

        $agg = $this->deployments($scope)->selectRaw(
            'count(*) as placements, '
            .'avg(ST_Distance(s.home_location::geography, ip.location::geography)) / 1000 as avg_km, '
            ."count(*) filter (where d.status = 'completed') as completed, "
            ."count(*) filter (where d.status = 'active' and ".self::SIGNED_SQL.' >= '.self::REQUIRED_SQL.') as active_past'
        )->first();

        $finalRating = $this->deployments($scope)
            ->join('evaluations as e', fn (JoinClause $j) => $j->on('e.deployment_id', '=', 'd.id')
                ->where('e.type', '=', EvaluationType::Final->value)->where('e.status', '=', 'reviewed'))
            ->avg('e.final_rating');

        $completionBase = (int) $agg->completed + (int) $agg->active_past;
        $slots = (int) $partners->slots;

        return [
            'placement_rate' => [
                'rate' => self::ratio((int) $students->placed, (int) $students->total),
                'numerator' => (int) $students->placed,
                'denominator' => (int) $students->total,
            ],
            'active_partners' => (int) $partners->partners,
            'partner_utilization' => [
                'rate' => self::ratio($filled, $slots),
                'filled' => $filled,
                'slots' => $slots,
            ],
            'avg_distance_km' => $agg->avg_km === null ? null : round((float) $agg->avg_km, 1),
            'completion_rate' => [
                'rate' => self::ratio((int) $agg->completed, $completionBase),
                'completed' => (int) $agg->completed,
                'active_past_required' => (int) $agg->active_past,
            ],
            'avg_final_rating' => [
                'value' => $finalRating === null ? null : round((float) $finalRating, 2),
                'descriptor' => EvaluationService::descriptor($finalRating),
            ],
            'placements' => (int) $agg->placements,
        ];
    }

    /**
     * Students in scope who are active OR were placed in the term (so past terms, whose
     * students may have graduated, still have a meaningful denominator). Columns: t.placed.
     *
     * @param  array<string, mixed>  $scope
     */
    public function studentPopulation(array $scope, bool $groupByProgram = false): QueryBuilder
    {
        [$start, $end] = $this->termDates($scope['term']);
        $inner = DB::table('students as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('u.role', UserRole::Student->value)
            ->when($scope['program_id'] !== null, fn (QueryBuilder $q) => $q->where('u.program_id', $scope['program_id']))
            ->when($scope['province'], fn (QueryBuilder $q) => $q->where('s.province', $scope['province']))
            ->selectRaw(
                "u.program_id, u.status, exists (select 1 from deployments x where x.student_id = s.id and x.status <> 'withdrawn' and x.start_date between ? and ?) as placed",
                [$start, $end],
            );

        return DB::query()->fromSub($inner, 't')->where(fn (QueryBuilder $q) => $q->where('t.status', UserStatus::Active->value)->orWhere('t.placed', true));
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array<string, mixed>>
     */
    private function byProvince(array $scope): array
    {
        $interns = $this->deployments($scope, withProvince: false)
            ->groupBy('ip.province')->selectRaw('ip.province, count(*) as n')->pluck('n', 'province');

        $partners = $this->activePartners($scope, withProvince: false)
            ->groupBy('ip.province')
            ->selectRaw('ip.province, count(*) as partners, coalesce(sum('.self::OPEN_SLOTS_SQL.'), 0) as open_slots')
            ->get()->keyBy('province');

        return array_map(fn (Province $p) => [
            'province' => $p->value,
            'interns' => (int) ($interns[$p->value] ?? 0),
            'partners' => (int) ($partners[$p->value]->partners ?? 0),
            'open_slots' => (int) ($partners[$p->value]->open_slots ?? 0),
            'underserved' => (int) ($interns[$p->value] ?? 0) === 0,
        ], Province::cases());
    }

    /**
     * The selected term and the 4 before it, oldest first. One query.
     *
     * @param  array<string, mixed>  $scope
     * @return list<array{key: string, label: string, placements: int}>
     */
    private function placementsTrend(array $scope): array
    {
        $terms = array_reverse(AcademicTerm::list(5, $scope['term']));
        $selects = [];
        $bindings = [];
        foreach ($terms as $i => $term) {
            $selects[] = "count(*) filter (where d.start_date between ? and ?) as t{$i}";
            array_push($bindings, ...$this->termDates($term));
        }
        $row = (array) $this->deployments($scope, withTerm: false)->selectRaw(implode(', ', $selects), $bindings)->first();

        return array_map(fn (AcademicTerm $term, int $i) => [
            'key' => $term->key(),
            'label' => $term->shortLabel(),
            'placements' => (int) $row["t{$i}"],
        ], $terms, array_keys($terms));
    }

    /**
     * Active deployments of the term by signed-hours progress.
     *
     * @param  array<string, mixed>  $scope
     * @return list<array{bucket: string, interns: int}>
     */
    private function hoursDistribution(array $scope): array
    {
        $inner = $this->deployments($scope)->where('d.status', 'active')
            ->selectRaw(self::SIGNED_SQL.' as signed, '.self::REQUIRED_SQL.' as required');

        $counts = DB::query()->fromSub($inner, 'x')
            ->selectRaw('case when x.required = 0 or x.signed >= x.required then 4 '
                .'when x.signed * 4 >= x.required * 3 then 3 '
                .'when x.signed * 2 >= x.required then 2 '
                .'when x.signed * 4 >= x.required then 1 else 0 end as bucket, count(*) as n')
            ->groupBy('bucket')
            ->pluck('n', 'bucket');

        return array_map(fn (string $label, int $i) => ['bucket' => $label, 'interns' => (int) ($counts[$i] ?? 0)], self::HOUR_BUCKETS, array_keys(self::HOUR_BUCKETS));
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array<string, mixed>>
     */
    private function workload(array $scope): array
    {
        $sub = $this->deployments($scope)->selectRaw('d.id, d.industry_partner_id, su.program_id');

        return DB::table('programs as pr')
            ->leftJoinSub($sub, 'x', 'x.program_id', '=', 'pr.id')
            ->when($scope['program_id'] !== null, fn (QueryBuilder $q) => $q->where('pr.id', $scope['program_id']))
            ->groupBy('pr.id', 'pr.code', 'pr.name')
            ->selectRaw(
                'pr.code, pr.name, '
                ."(select c.name from users c where c.role = 'coordinator' and c.status = 'active' and c.program_id = pr.id order by c.id limit 1) as coordinator, "
                .'count(x.id) as interns, count(distinct x.industry_partner_id) as partners_used'
            )
            ->orderBy('pr.code')
            ->get()
            ->map(fn (object $r) => [
                'program' => ['code' => $r->code, 'name' => $r->name],
                'coordinator' => $r->coordinator,
                'interns' => (int) $r->interns,
                'partners_used' => (int) $r->partners_used,
                'load' => self::load($r->coordinator, (int) $r->interns),
            ])->all();
    }

    public static function load(?string $coordinator, int $interns): string
    {
        return match (true) {
            $coordinator === null => 'no_coordinator',
            $interns > 20 => 'heavy',
            $interns >= 8 => 'balanced',
            default => 'light',
        };
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return list<array<string, mixed>>
     */
    private function topPartners(array $scope): array
    {
        return $this->deployments($scope)
            ->leftJoin('evaluations as e', fn (JoinClause $j) => $j->on('e.deployment_id', '=', 'd.id')
                ->where('e.type', '=', EvaluationType::Final->value)->where('e.status', '=', 'reviewed'))
            ->groupBy('ip.id', 'ip.company_name', 'ip.city')
            ->selectRaw('ip.company_name as company, ip.city, count(*) as interns, avg(e.final_rating) as avg_final')
            ->orderByDesc('interns')->orderBy('ip.company_name')
            ->limit(5)
            ->get()
            ->map(fn (object $r) => [
                'company' => $r->company,
                'city' => $r->city,
                'interns' => (int) $r->interns,
                'avg_final_rating' => $r->avg_final === null ? null : round((float) $r->avg_final, 2),
            ])->all();
    }

    /**
     * Per active skill: active partners needing it (required or preferred) vs active students
     * in scope who have it. Sorted by gap, largest first.
     *
     * @param  array<string, mixed>  $scope
     * @return list<array{skill: string, partners_need: int, students_have: int, gap: int}>
     */
    private function skillsGap(array $scope): array
    {
        $need = 'select count(*) from partner_skill ps join industry_partners ip on ip.id = ps.industry_partner_id '
            ."join users pu on pu.id = ip.user_id where ps.skill_id = sk.id and pu.status = 'active'";
        $have = 'select count(*) from student_skill ss join students s on s.id = ss.student_id '
            ."join users u on u.id = s.user_id where ss.skill_id = sk.id and u.role = 'student' and u.status = 'active'";
        $bindings = [];
        if ($scope['province']) {
            $need .= ' and ip.province = ?';
            $bindings[] = $scope['province'];
        }
        if ($scope['program_id'] !== null) {
            $have .= ' and u.program_id = ?';
            $bindings[] = $scope['program_id'];
        }
        if ($scope['province']) {
            $have .= ' and s.province = ?';
            $bindings[] = $scope['province'];
        }

        $rows = DB::table('skills as sk')->where('sk.is_active', true)
            ->selectRaw("sk.name, ({$need}) as need, ({$have}) as have", $bindings)
            ->get()
            ->map(fn (object $r) => [
                'skill' => $r->name,
                'partners_need' => (int) $r->need,
                'students_have' => (int) $r->have,
                'gap' => (int) $r->need - (int) $r->have,
            ])->all();

        usort($rows, fn (array $a, array $b) => [$b['gap'], $a['skill']] <=> [$a['gap'], $b['skill']]);

        return $rows;
    }

    /**
     * Final evaluations of the term's deployments in scope (Module 7 summary).
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function evaluation(User $viewer, array $scope): array
    {
        $summary = $this->evaluationReports->summary($viewer, EvaluationType::Final, $this->deployments($scope)->select('d.id'));

        return [
            'evaluated' => $summary['evaluations_submitted'],
            'average_partner_mean' => $summary['average_partner_mean'],
            'average_descriptor' => $summary['average_descriptor'],
            'criteria' => $summary['criteria'],
            'lowest_criterion' => $summary['lowest_criterion'],
        ];
    }

    /**
     * How often the partner the coordinator actually chose was ranked 1st / top 3 by each
     * algorithm. Manual placements are excluded from agreement; feedback is the coordinators'
     * 1–5 ratings given during the term for students in scope.
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function algorithmComparison(array $scope): array
    {
        $ranked = "coalesce(d.rank_cos, d.rank_wsm, d.rank_fuzzy) is not null and d.algorithm_used is distinct from 'manual'";
        $selects = [
            "count(*) filter (where d.algorithm_used = 'manual') as manual",
            "count(*) filter (where {$ranked}) as n",
        ];
        foreach (array_keys(self::ALGORITHMS) as $a) {
            $selects[] = "count(*) filter (where d.algorithm_used = '{$a}') as {$a}_using";
            $selects[] = "count(*) filter (where {$ranked} and d.rank_{$a} = 1) as {$a}_top1";
            $selects[] = "count(*) filter (where {$ranked} and d.rank_{$a} <= 3) as {$a}_top3";
            $selects[] = "avg(d.rank_{$a}) filter (where {$ranked}) as {$a}_mean";
        }
        $row = (array) $this->deployments($scope)->selectRaw(implode(', ', $selects))->first();

        [$start, $end] = $this->termDates($scope['term']);
        $feedback = DB::table('recommendation_feedback as f')
            ->join('students as s', 's.id', '=', 'f.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('f.created_at', '>=', $start)
            ->where('f.created_at', '<', $scope['term']->end->addDay()->toDateString())
            ->when($scope['program_id'] !== null, fn (QueryBuilder $q) => $q->where('u.program_id', $scope['program_id']))
            ->when($scope['province'], fn (QueryBuilder $q) => $q->where('s.province', $scope['province']))
            ->groupBy('f.algorithm')
            ->selectRaw('f.algorithm, avg(f.rating) as average, count(*) as n')
            ->get()->keyBy('algorithm');

        $n = (int) $row['n'];
        $algorithms = [];
        foreach (self::ALGORITHMS as $a => $label) {
            $algorithms[] = [
                'algorithm' => $a,
                'label' => $label,
                'placements_using' => (int) $row["{$a}_using"],
                'top1_agreement' => $n > 0 ? round((int) $row["{$a}_top1"] / $n * 100, 1) : null,
                'top3_agreement' => $n > 0 ? round((int) $row["{$a}_top3"] / $n * 100, 1) : null,
                'mean_rank_of_chosen' => $row["{$a}_mean"] === null ? null : round((float) $row["{$a}_mean"], 2),
                'avg_feedback_rating' => isset($feedback[$a]) ? round((float) $feedback[$a]->average, 2) : null,
                'feedback_n' => (int) ($feedback[$a]->n ?? 0),
            ];
        }

        return [
            'algorithms' => $algorithms,
            'total_with_rank_data' => $n,
            'manual_placements' => (int) $row['manual'],
        ];
    }

    /**
     * Per program for the program summary report: the KPIs and evaluation averages. A fixed
     * number of grouped queries, whatever the number of programs.
     *
     * @param  array<string, mixed>  $scope
     * @return list<array<string, mixed>>
     */
    public function programSummaries(array $scope): array
    {
        $students = $this->studentPopulation($scope)
            ->groupBy('t.program_id')
            ->selectRaw('t.program_id, count(*) filter (where t.placed) as placed, count(*) as total')
            ->get()->keyBy('program_id');

        $criteria = array_keys(EvaluationService::criteria());
        $criteriaSql = implode(', ', array_map(
            fn (int $i) => "avg((e.ratings->>?)::numeric) filter (where e.status in ('submitted', 'reviewed')) as c{$i}",
            array_keys($criteria),
        ));

        $deployments = $this->deployments($scope)
            ->leftJoin('evaluations as e', fn (JoinClause $j) => $j->on('e.deployment_id', '=', 'd.id')
                ->where('e.type', '=', EvaluationType::Final->value))
            ->groupBy('su.program_id')
            ->selectRaw(
                'su.program_id, count(*) as placements, count(distinct d.industry_partner_id) as partners_used, '
                .'avg(ST_Distance(s.home_location::geography, ip.location::geography)) / 1000 as avg_km, '
                ."count(*) filter (where d.status = 'completed') as completed, "
                ."count(*) filter (where d.status = 'active' and ".self::SIGNED_SQL.' >= '.self::REQUIRED_SQL.') as active_past, '
                ."avg(e.final_rating) filter (where e.status = 'reviewed') as avg_final, "
                ."avg(e.partner_mean) filter (where e.status in ('submitted', 'reviewed')) as avg_partner_mean, "
                .$criteriaSql,
                $criteria,
            )
            ->get()->keyBy('program_id');

        return DB::table('programs as pr')
            ->when($scope['program_id'] !== null, fn (QueryBuilder $q) => $q->where('pr.id', $scope['program_id']))
            ->selectRaw("pr.id, pr.code, pr.name, (select c.name from users c where c.role = 'coordinator' and c.status = 'active' and c.program_id = pr.id order by c.id limit 1) as coordinator")
            ->orderBy('pr.code')
            ->get()
            ->map(function (object $p) use ($students, $deployments, $criteria) {
                $st = $students[$p->id] ?? null;
                $d = $deployments[$p->id] ?? null;
                $completed = (int) ($d->completed ?? 0);
                $finalRating = $d?->avg_final === null ? null : round((float) $d->avg_final, 2);
                $perCriterion = [];
                foreach ($criteria as $i => $key) {
                    $value = $d === null ? null : ($d->{"c{$i}"} ?? null);
                    $perCriterion[$key] = $value === null ? null : round((float) $value, 2);
                }

                return [
                    'program' => ['code' => $p->code, 'name' => $p->name],
                    'coordinator' => $p->coordinator,
                    'students' => (int) ($st->total ?? 0),
                    'placed' => (int) ($st->placed ?? 0),
                    'placement_rate' => self::ratio((int) ($st->placed ?? 0), (int) ($st->total ?? 0)),
                    'placements' => (int) ($d->placements ?? 0),
                    'partners_used' => (int) ($d->partners_used ?? 0),
                    'avg_distance_km' => $d?->avg_km === null ? null : round((float) $d->avg_km, 1),
                    'completion_rate' => self::ratio($completed, $completed + (int) ($d->active_past ?? 0)),
                    'avg_final_rating' => $finalRating,
                    'descriptor' => EvaluationService::descriptor($finalRating),
                    'avg_partner_mean' => $d?->avg_partner_mean === null ? null : round((float) $d->avg_partner_mean, 2),
                    'criteria' => $perCriterion,
                ];
            })->all();
    }

    /** Percentage with one decimal, or null when the denominator is 0. */
    public static function ratio(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
    }
}
