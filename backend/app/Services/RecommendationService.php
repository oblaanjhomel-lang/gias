<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\IndustryPartner;
use App\Models\Skill;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranks Industry Partners for students with three recommenders (cosine similarity, WSM, fuzzy).
 * ADVISORY ONLY: nothing here creates a deployment; see PlacementService.
 *
 * Public ids are USER ids (students and partners), as everywhere else in the API.
 * Candidates: active partners with open slots (open = intern_slots − active deployments),
 * plus the partner where the student is already actively placed.
 */
class RecommendationService
{
    public const ALGORITHMS = ['cos', 'wsm', 'fuzzy'];

    public function __construct(
        private readonly CosineSimilarityService $cosine,
        private readonly WeightedSumService $wsm,
        private readonly FuzzyLogicService $fuzzy,
        private readonly RecommendationSettingsService $settings,
    ) {}

    /**
     * Full recommendation for one student: rankings per algorithm, comparison, agreement,
     * explanations, and the full partners left out.
     *
     * @return array<string, mixed>
     */
    public function recommendFor(User $studentUser, User $viewer): array
    {
        $student = $this->studentProfile($studentUser);
        $student->load(['user.program', 'activeDeployment.industryPartner']);

        $skills = $this->activeSkills();
        $settings = $this->settings->values();
        $placedAt = $student->activeDeployment?->industry_partner_id;

        [$candidates, $excluded] = $this->partnerPool()
            ->partition(fn (IndustryPartner $p) => $p->open > 0 || $p->id === $placedAt);

        $needs = $this->partnerNeeds($candidates->modelKeys());
        $levels = $this->studentLevels([$student->id])[$student->id] ?? [];
        $distances = $this->distances([$student->id], $candidates->modelKeys())[$student->id] ?? [];

        $rows = $candidates->map(fn (IndustryPartner $p) => [
            'partner' => $p,
            'distance_km' => $distances[$p->id] ?? null,
            'needs' => $needs[$p->id] ?? [],
            'results' => $this->evaluate($skills, $levels, $needs[$p->id] ?? [], $distances[$p->id] ?? null, $p->open, $settings),
        ])->values()->all();

        $rankings = [];
        foreach (self::ALGORITHMS as $algorithm) {
            $rankings[$algorithm] = [];
            foreach ($this->sortRows($rows, $algorithm) as $i => $row) {
                $rankings[$algorithm][] = [
                    'partner_id' => $row['partner']->user_id,
                    'score' => round($row['results'][$algorithm]['score'], 4),
                    'rank' => $i + 1,
                ];
            }
        }

        $rankOf = [];
        foreach ($rankings as $algorithm => $list) {
            foreach ($list as $entry) {
                $rankOf[$algorithm][$entry['partner_id']] = $entry;
            }
        }

        $partners = [];
        $explanations = [];
        $compare = [];
        foreach ($rows as $row) {
            /** @var IndustryPartner $p */
            $p = $row['partner'];
            $id = $p->user_id;
            $needed = count($row['needs']);
            $matched = count(array_filter(array_keys($row['needs']), fn (int $k) => ($levels[$k] ?? 0) > 0 && isset($skills[$k])));

            $partners[] = [
                'id' => $id,
                'company_name' => $p->company_name,
                'city' => $p->city,
                'province' => $p->province->value,
                'distance_km' => $row['distance_km'] === null ? null : round($row['distance_km'], 1),
                'intern_slots' => $p->intern_slots,
                'open' => $p->open,
                'skills_needed' => count(array_intersect_key($row['needs'], $skills)),
                'skills_matched' => $matched,
                'is_current_placement' => $p->id === $placedAt,
            ];

            // A list (not keyed by id): API resources re-index integer-keyed arrays.
            $explanations[] = ['partner_id' => $id] + array_map(
                fn (array $result) => ['score' => round($result['score'], 4)] + $result['breakdown'],
                $row['results'],
            );

            $ranks = array_map(fn (string $a) => $rankOf[$a][$id]['rank'], self::ALGORITHMS);
            $compare[] = [
                'partner_id' => $id,
                'company_name' => $p->company_name,
                'cos' => ['score' => $rankOf['cos'][$id]['score'], 'rank' => $rankOf['cos'][$id]['rank']],
                'wsm' => ['score' => $rankOf['wsm'][$id]['score'], 'rank' => $rankOf['wsm'][$id]['rank']],
                'fuzzy' => ['score' => $rankOf['fuzzy'][$id]['score'], 'rank' => $rankOf['fuzzy'][$id]['rank']],
                'average_rank' => round(array_sum($ranks) / count($ranks), 2),
            ];
        }
        usort($compare, fn (array $a, array $b) => [$a['average_rank'], $a['company_name']] <=> [$b['average_rank'], $b['company_name']]);

        $top = array_map(fn (array $list) => $list[0]['partner_id'] ?? null, $rankings);

        return [
            'student' => $this->studentSummary($student, $skills),
            'partners' => $partners,
            'rankings' => $rankings,
            'compare' => $compare,
            'agreement' => [
                'all_agree' => ! in_array(null, $top, true) && count(array_unique($top)) === 1,
                'top' => $top,
            ],
            'excluded' => $excluded->map(fn (IndustryPartner $p) => [
                'id' => $p->user_id,
                'company_name' => $p->company_name,
                'city' => $p->city,
                'intern_slots' => $p->intern_slots,
            ])->values()->all(),
            'explanations' => $explanations,
            'settings' => $settings,
        ];
    }

    /**
     * What gets stored with a deployment / feedback: each algorithm's ranked list with names.
     *
     * @param  array<string, mixed>  $result  recommendFor() output
     * @return array<string, mixed>
     */
    public function snapshot(array $result, ?string $only = null): array
    {
        $names = array_column($result['partners'], 'company_name', 'id');
        $lists = [];
        foreach ($only ? [$only] : self::ALGORITHMS as $algorithm) {
            $lists[$algorithm] = array_map(fn (array $e) => $e + ['company_name' => $names[$e['partner_id']]], $result['rankings'][$algorithm]);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'settings' => $result['settings'],
            'rankings' => $lists,
        ];
    }

    /**
     * Active students in the viewer's scope (placed and unplaced) with their placement and
     * top skills; unplaced first. Includes the placed/total counts for the queue header.
     *
     * @return array{students: Collection<int, Student>, placed: int, total: int}
     */
    public function studentsForPlacement(User $viewer): array
    {
        $students = $this->studentsInScope($viewer)
            ->with([
                'user.program',
                'skills' => fn ($q) => $q->where('is_active', true)->orderByDesc('student_skill.level')->orderBy('name'),
                'activeDeployment.industryPartner',
            ])
            ->get()
            ->sortBy(fn (Student $s) => [$s->activeDeployment ? 1 : 0, $s->user->name])
            ->values();

        return [
            'students' => $students,
            'placed' => $students->filter(fn (Student $s) => $s->activeDeployment !== null)->count(),
            'total' => $students->count(),
        ];
    }

    /**
     * A greedy plan for every unplaced student in scope. NOTHING is saved.
     * All (student, partner) scores sorted high → low; assign while the partner has capacity,
     * one partner per student. Pairs scoring 0 are never proposed.
     *
     * @return array{algorithm: string, proposals: list<array<string, mixed>>, unassigned: list<array<string, mixed>>}
     */
    public function draft(User $viewer, string $algorithm): array
    {
        $students = $this->studentsInScope($viewer)
            ->whereDoesntHave('activeDeployment')
            ->with('user.program')
            ->get();
        $partners = $this->partnerPool()->filter(fn (IndustryPartner $p) => $p->open > 0)->values();

        $skills = $this->activeSkills();
        $settings = $this->settings->values();
        $needs = $this->partnerNeeds($partners->modelKeys());
        $levels = $this->studentLevels($students->modelKeys());
        $distances = $this->distances($students->modelKeys(), $partners->modelKeys());

        $pairs = [];
        foreach ($students as $student) {
            foreach ($partners as $partner) {
                $km = $distances[$student->id][$partner->id] ?? null;
                $score = $this->evaluate(
                    $skills, $levels[$student->id] ?? [], $needs[$partner->id] ?? [], $km, $partner->open, $settings, $algorithm,
                )[$algorithm]['score'];

                if ($score > 0) {
                    $pairs[] = ['student' => $student, 'partner' => $partner, 'score' => $score, 'km' => $km];
                }
            }
        }

        usort($pairs, fn (array $a, array $b) => [-$a['score'], $a['km'] ?? INF, $a['student']->user->name, $a['partner']->company_name]
            <=> [-$b['score'], $b['km'] ?? INF, $b['student']->user->name, $b['partner']->company_name]);

        $capacity = $partners->mapWithKeys(fn (IndustryPartner $p) => [$p->id => $p->open])->all();
        $assigned = [];
        $proposals = [];
        foreach ($pairs as $pair) {
            $studentId = $pair['student']->id;
            $partnerId = $pair['partner']->id;
            if (isset($assigned[$studentId]) || $capacity[$partnerId] <= 0) {
                continue;
            }
            $assigned[$studentId] = true;
            $capacity[$partnerId]--;
            $proposals[] = [
                'student' => $this->studentBrief($pair['student']),
                'partner' => [
                    'id' => $pair['partner']->user_id,
                    'company_name' => $pair['partner']->company_name,
                    'city' => $pair['partner']->city,
                ],
                'score' => round($pair['score'], 4),
                'distance_km' => $pair['km'] === null ? null : round($pair['km'], 1),
            ];
        }

        return [
            'algorithm' => $algorithm,
            'proposals' => $proposals,
            'unassigned' => $students
                ->reject(fn (Student $s) => isset($assigned[$s->id]))
                ->map(fn (Student $s) => $this->studentBrief($s))
                ->sortBy('name')
                ->values()
                ->all(),
        ];
    }

    /**
     * Student view: the top 3 partners with open slots by WSM. No breakdowns.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestionsForStudent(User $studentUser): array
    {
        $student = $this->studentProfile($studentUser);
        $partners = $this->partnerPool()->filter(fn (IndustryPartner $p) => $p->open > 0)->values();

        $skills = $this->activeSkills();
        $settings = $this->settings->values();
        $needs = $this->partnerNeeds($partners->modelKeys());
        $levels = $this->studentLevels([$student->id])[$student->id] ?? [];
        $distances = $this->distances([$student->id], $partners->modelKeys())[$student->id] ?? [];

        $rows = $partners->map(fn (IndustryPartner $p) => [
            'partner' => $p,
            'distance_km' => $distances[$p->id] ?? null,
            'results' => $this->evaluate($skills, $levels, $needs[$p->id] ?? [], $distances[$p->id] ?? null, $p->open, $settings, 'wsm'),
        ])->all();

        return array_map(fn (array $row) => [
            'id' => $row['partner']->user_id,
            'company_name' => $row['partner']->company_name,
            'city' => $row['partner']->city,
            'province' => $row['partner']->province->value,
            'distance_km' => $row['distance_km'] === null ? null : round($row['distance_km'], 1),
            'open' => $row['partner']->open,
            'score' => round($row['results']['wsm']['score'], 4),
        ], array_slice($this->sortRows($rows, 'wsm'), 0, 3));
    }

    /**
     * Partner view: the top 3 unplaced active students by WSM for this partner.
     * Names and program only: no contact details.
     *
     * @return array{students: list<array{name: string, program: string|null, score: float}>, open: int, intern_slots: int}
     */
    public function matchingStudentsForPartner(User $partnerUser): array
    {
        $partner = $this->partnerPool()->firstWhere('user_id', $partnerUser->id)
            ?? $this->withOpen($partnerUser->industryPartner()->withCount($this->filledCount())->firstOrFail());

        $students = Student::query()
            ->whereHas('user', fn (Builder $q) => $q->where('role', UserRole::Student)->where('status', UserStatus::Active))
            ->whereDoesntHave('activeDeployment')
            ->with('user.program')
            ->get();

        $skills = $this->activeSkills();
        $settings = $this->settings->values();
        $needs = $this->partnerNeeds([$partner->id])[$partner->id] ?? [];
        $levels = $this->studentLevels($students->modelKeys());
        $distances = $this->distances($students->modelKeys(), [$partner->id]);

        $scored = $students->map(fn (Student $s) => [
            'name' => $s->user->name,
            'program' => $s->user->program?->code,
            'score' => $this->evaluate(
                $skills, $levels[$s->id] ?? [], $needs, $distances[$s->id][$partner->id] ?? null, $partner->open, $settings, 'wsm',
            )['wsm']['score'],
        ])->sortBy([['score', 'desc'], ['name', 'asc']])->take(3)->values();

        return [
            'students' => $scored->map(fn (array $s) => ['name' => $s['name'], 'program' => $s['program'], 'score' => round($s['score'], 4)])->all(),
            'open' => $partner->open,
            'intern_slots' => $partner->intern_slots,
        ];
    }

    // Building blocks

    /**
     * Run the algorithms ($only = one of them) for one (student, partner) pair.
     *
     * @return array<string, array{score: float, breakdown: array<string, mixed>}>
     */
    private function evaluate(array $skills, array $levels, array $needs, ?float $km, int $open, array $settings, ?string $only = null): array
    {
        $results = [];
        if ($only === null || $only === 'cos') {
            $results['cos'] = $this->cosine->score($skills, $levels, $needs);
        }
        if ($only === null || $only === 'wsm') {
            $results['wsm'] = $this->wsm->score($skills, $levels, $needs, $km, $open, $settings);
        }
        if ($only === null || $only === 'fuzzy') {
            $results['fuzzy'] = $this->fuzzy->score($skills, $levels, $needs, $km);
        }

        return $results;
    }

    /**
     * Score desc, then distance asc (unknown last), then company name.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortRows(array $rows, string $algorithm): array
    {
        usort($rows, fn (array $a, array $b) => [
            -round($a['results'][$algorithm]['score'], 10), $a['distance_km'] ?? INF, $a['partner']->company_name,
        ] <=> [
            -round($b['results'][$algorithm]['score'], 10), $b['distance_km'] ?? INF, $b['partner']->company_name,
        ]);

        return $rows;
    }

    /**
     * Active partners with `filled` and `open` (= max(slots − active deployments, 0)).
     *
     * @return Collection<int, IndustryPartner>
     */
    private function partnerPool(): Collection
    {
        return IndustryPartner::query()
            ->whereHas('user', fn (Builder $q) => $q->where('role', UserRole::Partner)->where('status', UserStatus::Active))
            ->withCount($this->filledCount())
            ->orderBy('company_name')
            ->get()
            ->each(fn (IndustryPartner $p) => $this->withOpen($p));
    }

    /**
     * @return array<string, \Closure>
     */
    private function filledCount(): array
    {
        return ['deployments as filled' => fn (Builder $q) => $q->where('status', DeploymentStatus::Active)];
    }

    private function withOpen(IndustryPartner $partner): IndustryPartner
    {
        $partner->setAttribute('open', max($partner->intern_slots - (int) $partner->filled, 0));

        return $partner;
    }

    /**
     * @return array<int, string> active skills: id => name
     */
    private function activeSkills(): array
    {
        return Skill::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @param  list<int>  $partnerIds  industry_partners.id
     * @return array<int, array<int, int>> partner id => [skill id => importance]
     */
    private function partnerNeeds(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        $needs = [];
        DB::table('partner_skill')
            ->join('skills', 'skills.id', '=', 'partner_skill.skill_id')
            ->where('skills.is_active', true)
            ->whereIn('partner_skill.industry_partner_id', $partnerIds)
            ->get(['partner_skill.industry_partner_id', 'partner_skill.skill_id', 'partner_skill.importance'])
            ->each(function (object $row) use (&$needs) {
                $needs[$row->industry_partner_id][$row->skill_id] = (int) $row->importance;
            });

        return $needs;
    }

    /**
     * @param  list<int>  $studentIds  students.id
     * @return array<int, array<int, int>> student id => [skill id => level]
     */
    private function studentLevels(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $levels = [];
        DB::table('student_skill')
            ->join('skills', 'skills.id', '=', 'student_skill.skill_id')
            ->where('skills.is_active', true)
            ->whereIn('student_skill.student_id', $studentIds)
            ->get(['student_skill.student_id', 'student_skill.skill_id', 'student_skill.level'])
            ->each(function (object $row) use (&$levels) {
                $levels[$row->student_id][$row->skill_id] = (int) $row->level;
            });

        return $levels;
    }

    /**
     * Every (student, partner) distance in ONE PostGIS query: ST_Distance on geography, in km.
     * null when either point is missing.
     *
     * @param  list<int>  $studentIds  students.id
     * @param  list<int>  $partnerIds  industry_partners.id
     * @return array<int, array<int, float|null>> student id => [partner id => km]
     */
    private function distances(array $studentIds, array $partnerIds): array
    {
        if ($studentIds === [] || $partnerIds === []) {
            return [];
        }

        $matrix = [];
        DB::table('students as s')
            ->crossJoin('industry_partners as ip')
            ->whereIn('s.id', $studentIds)
            ->whereIn('ip.id', $partnerIds)
            ->selectRaw('s.id as student_id, ip.id as partner_id, '
                .'ST_Distance(s.home_location::geography, ip.location::geography) / 1000.0 as km')
            ->get()
            ->each(function (object $row) use (&$matrix) {
                $matrix[$row->student_id][$row->partner_id] = $row->km === null ? null : (float) $row->km;
            });

        return $matrix;
    }

    /**
     * @return Builder<Student>
     */
    private function studentsInScope(User $viewer): Builder
    {
        return Student::query()->whereHas('user', fn (Builder $q) => $q
            ->where('role', UserRole::Student)
            ->where('status', UserStatus::Active)
            ->when($viewer->role !== UserRole::Admin, fn (Builder $q) => $viewer->program_id !== null
                ? $q->where('program_id', $viewer->program_id)
                : $q->whereRaw('false')));
    }

    private function studentProfile(User $studentUser): Student
    {
        return $studentUser->student()->firstOrFail();
    }

    /**
     * @return array{id: int, name: string, program: string|null}
     */
    private function studentBrief(Student $student): array
    {
        return [
            'id' => $student->user_id,
            'name' => $student->user->name,
            'program' => $student->user->program?->code,
        ];
    }

    /**
     * @param  array<int, string>  $skills
     * @return array<string, mixed>
     */
    private function studentSummary(Student $student, array $skills): array
    {
        $levels = $this->studentLevels([$student->id])[$student->id] ?? [];
        $list = [];
        foreach ($levels as $skillId => $level) {
            if (isset($skills[$skillId])) {
                $list[] = ['skill_id' => $skillId, 'name' => $skills[$skillId], 'level' => $level];
            }
        }
        usort($list, fn (array $a, array $b) => [$b['level'], $a['name']] <=> [$a['level'], $b['name']]);

        $deployment = $student->activeDeployment;

        return [
            'id' => $student->user_id,
            'name' => $student->user->name,
            'program' => $student->user->program ? [
                'code' => $student->user->program->code,
                'name' => $student->user->program->name,
            ] : null,
            'city' => $student->city,
            'province' => $student->province->value,
            'has_home_location' => $student->home_location !== null,
            'skills' => $list,
            'placement' => $deployment ? [
                'deployment_id' => $deployment->id,
                'partner_id' => $deployment->industryPartner->user_id,
                'company_name' => $deployment->industryPartner->company_name,
                'start_date' => $deployment->start_date?->toDateString(),
            ] : null,
        ];
    }
}
