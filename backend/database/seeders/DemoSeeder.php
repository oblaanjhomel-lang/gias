<?php

namespace Database\Seeders;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationStatus;
use App\Enums\EvaluationType;
use App\Enums\GeocodeStatus;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WeeklyReportStatus;
use App\Models\Deployment;
use App\Models\Evaluation;
use App\Models\IndustryPartner;
use App\Models\Program;
use App\Models\RecommendationFeedback;
use App\Models\Skill;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\EvaluationService;
use App\Services\WeeklyReportService;
use App\Support\AcademicTerm;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo data for the GIS map: coordinators, partners with real coordinates, students and
 * deployments. LOCAL ONLY and not called from DatabaseSeeder:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Idempotent (updateOrCreate on email) and deterministic (fixed random seed).
 * Every demo account's password is "password".
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const START_DATE = '2026-09-01';

    /**
     * Active partners: [company, city, province, lat, lng, slots, interns to deploy].
     * Interns total 45: Mabini is full; Water District, PPDO, Kapalong and Dujali are nearly full.
     */
    private const PARTNERS = [
        ['Davao Agri-Ventures Packing Plant', 'Panabo City', Province::DavaoDelNorte, 7.3081, 125.6841, 12, 6],
        ['Tagum City IT Solutions Hub', 'Tagum City', Province::DavaoDelNorte, 7.4478, 125.8078, 15, 9],
        ['Northern Davao Water District', 'Tagum City', Province::DavaoDelNorte, 7.4390, 125.7960, 6, 5],
        ['Provincial Planning & Development Office', 'Tagum City', Province::DavaoDelNorte, 7.4520, 125.8140, 8, 6],
        ['Samal Island Resort & Hospitality Group', 'Island Garden City of Samal', Province::DavaoDelNorte, 7.0500, 125.7167, 10, 4],
        ['Mindanao Food Processing Corp.', 'Panabo City', Province::DavaoDelNorte, 7.3210, 125.6640, 9, 3],
        ['Carmen Municipal Agriculture Office', 'Carmen', Province::DavaoDelNorte, 7.3450, 125.7060, 5, 2],
        ['Sto. Tomas Cooperative Bank', 'Sto. Tomas', Province::DavaoDelNorte, 7.5333, 125.6167, 6, 0],
        ['Davao City Digital Services Inc.', 'Davao City', Province::DavaoCity, 7.1907, 125.4553, 20, 2],
        ['Southern Mindanao Medical Center', 'Davao City', Province::DavaoCity, 7.0770, 125.5100, 7, 1],
        ['Kapalong Agri-Tech Cooperative', 'Kapalong', Province::DavaoDelNorte, 7.5833, 125.7000, 4, 2],
        ['Nabunturan Rural Development Office', 'Nabunturan', Province::DavaoDeOro, 7.6047, 125.9645, 5, 0],
        ['Mati Coastal Resource Center', 'Mati City', Province::DavaoOriental, 6.9550, 126.2167, 4, 0],
        ['Mabini Mining Community Relations Office', 'Mabini', Province::DavaoDeOro, 7.3167, 125.8500, 3, 3],
        ['B.E. Dujali LGU MIS Division', 'B.E. Dujali', Province::DavaoDelNorte, 7.4333, 125.6833, 4, 2],
        ['Asuncion Integrated Farm School', 'Asuncion', Province::DavaoDelNorte, 7.5833, 125.7500, 5, 0],
    ];

    /** Pending (awaiting approval): [company, city, province, lat, lng, slots]. */
    private const PENDING_PARTNERS = [
        ['Digos Valley Food Corp.', 'Digos City', Province::DavaoDelSur, 6.7496, 125.3572, 6],
        ['Malita Agro-Industrial Estate', 'Malita', Province::DavaoOccidental, 6.4123, 125.6111, 8],
    ];

    /** Active partner whose address could not be geocoded. */
    private const UNLOCATED_PARTNER = ['Pantukan Coastal Enterprises', 'Pantukan', Province::DavaoDeOro, 5];

    /** Students' home towns: [city, province, lat, lng]. Homes get a small random jitter. */
    private const TOWNS = [
        ['Tagum City', Province::DavaoDelNorte, 7.4478, 125.8078],
        ['Panabo City', Province::DavaoDelNorte, 7.3081, 125.6841],
        ['Carmen', Province::DavaoDelNorte, 7.3600, 125.7050],
        ['Sto. Tomas', Province::DavaoDelNorte, 7.5333, 125.6167],
        ['Kapalong', Province::DavaoDelNorte, 7.5833, 125.7000],
        ['New Corella', Province::DavaoDelNorte, 7.5866, 125.8237],
        ['B.E. Dujali', Province::DavaoDelNorte, 7.4333, 125.6833],
        ['Asuncion', Province::DavaoDelNorte, 7.5833, 125.7500],
        ['Davao City', Province::DavaoCity, 7.0731, 125.6128],
        ['Island Garden City of Samal', Province::DavaoDelNorte, 7.0500, 125.7167],
    ];

    private const STUDENT_COUNT = 60;

    /** The last students get no home location (geocode failed) and stay unplaced. */
    private const STUDENTS_WITHOUT_HOME = 2;

    private const FIRST_NAMES = [
        'Andrea', 'Bryan', 'Camille', 'Daniel', 'Erika', 'Francis', 'Grace', 'Harold',
        'Ivy', 'Jerome', 'Kristine', 'Lorenzo', 'Mae', 'Nathaniel', 'Olivia',
    ];

    private const LAST_NAMES = ['Dela Cruz', 'Reyes', 'Santos', 'Bautista', 'Garcia', 'Mendoza', 'Villanueva', 'Ramos'];

    private const COORDINATOR_NAMES = [
        'Rosalie Abarquez', 'Edgardo Lumbay', 'Marivic Tan', 'Joel Sumalinog', 'Cherry Mae Ocampo', 'Ramil Dagohoy',
    ];

    private const CONTACT_NAMES = [
        'Liza Mercado', 'Arnel Cabrera', 'Jocelyn Uy', 'Ronaldo Pacquing', 'Teresita Lim', 'Manuel Yap',
    ];

    private string $passwordHash;

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder only runs when APP_ENV=local. It creates demo accounts with the password "password".');
        }

        mt_srand(20261119); // same data on every run

        $this->call([ProgramSeeder::class, SkillSeeder::class]);
        $this->passwordHash = Hash::make(self::PASSWORD); // hash once; the model cast keeps hashed values

        DB::transaction(function () {
            $programs = Program::query()->orderBy('code')->get();
            $coordinators = $this->coordinators($programs);
            $partners = $this->partners();
            $students = $this->students($programs);
            $deployments = $this->deployments($students, $partners, $coordinators);
            $this->partnerSkills();
            $this->studentSkills($students);
            $this->weeklyReports($deployments);
            $this->evaluations($deployments);
            $this->algorithmData($deployments);
            $this->history($programs, $partners, $coordinators);
            $this->feedback($students, $coordinators);
        });
    }

    /** Short codes → SkillSeeder names. */
    private const SKILL = [
        'web' => 'Web development (PHP/Laravel)',
        'frontend' => 'Frontend (Vue/React)',
        'database' => 'Database & SQL',
        'networking' => 'Networking',
        'support' => 'Technical support',
        'hardware' => 'Hardware troubleshooting',
        'gis' => 'GIS & mapping',
        'data' => 'Data analysis',
        'ui' => 'Graphic & UI design',
        'mobile' => 'Mobile development',
        'security' => 'Cybersecurity basics',
        'writing' => 'Technical writing',
    ];

    /** What each demo partner needs (2 = required, 1 = preferred), by the kind of organisation. */
    private const PARTNER_SKILLS = [
        'Davao Agri-Ventures Packing Plant' => ['data' => 2, 'writing' => 1, 'support' => 1, 'database' => 1],
        'Tagum City IT Solutions Hub' => ['web' => 2, 'frontend' => 2, 'database' => 2, 'ui' => 1, 'mobile' => 1],
        'Northern Davao Water District' => ['data' => 2, 'networking' => 2, 'support' => 1, 'database' => 1],
        'Provincial Planning & Development Office' => ['gis' => 2, 'data' => 2, 'writing' => 1],
        'Samal Island Resort & Hospitality Group' => ['support' => 1, 'ui' => 1, 'web' => 1],
        'Mindanao Food Processing Corp.' => ['data' => 2, 'writing' => 1, 'database' => 1],
        'Carmen Municipal Agriculture Office' => ['data' => 2, 'writing' => 2, 'gis' => 1],
        'Sto. Tomas Cooperative Bank' => ['database' => 2, 'security' => 2, 'support' => 1, 'networking' => 1],
        'Davao City Digital Services Inc.' => ['web' => 2, 'frontend' => 2, 'mobile' => 2, 'database' => 1, 'security' => 1, 'ui' => 1],
        'Southern Mindanao Medical Center' => ['database' => 2, 'support' => 2, 'networking' => 1, 'security' => 1],
        'Kapalong Agri-Tech Cooperative' => ['data' => 2, 'mobile' => 1, 'support' => 1],
        'Nabunturan Rural Development Office' => ['gis' => 2, 'data' => 1, 'writing' => 1],
        'Mati Coastal Resource Center' => ['gis' => 2, 'data' => 2, 'writing' => 1],
        'Mabini Mining Community Relations Office' => ['writing' => 2, 'data' => 1, 'ui' => 1],
        'B.E. Dujali LGU MIS Division' => ['networking' => 2, 'hardware' => 2, 'support' => 2, 'database' => 1],
        'Asuncion Integrated Farm School' => ['writing' => 1, 'data' => 1, 'ui' => 1],
        'Digos Valley Food Corp.' => ['data' => 2, 'writing' => 1, 'database' => 1],
        'Malita Agro-Industrial Estate' => ['data' => 2, 'gis' => 1, 'writing' => 1],
        'Pantukan Coastal Enterprises' => ['support' => 2, 'networking' => 1, 'hardware' => 1],
    ];

    /** Skills students of each program tend to have; others use the default pool. */
    private const IT_POOL = ['web', 'frontend', 'database', 'networking', 'support', 'hardware', 'mobile', 'security', 'ui'];

    private const FIELD_SCIENCE_POOL = ['gis', 'data', 'writing', 'ui', 'database'];

    private const STUDENT_SKILL_POOLS = [
        'BSIT' => self::IT_POOL,
        'BSIS' => self::IT_POOL,
        'BSDRM' => self::FIELD_SCIENCE_POOL,
        'BSAF' => self::FIELD_SCIENCE_POOL,
        'BSMB' => self::FIELD_SCIENCE_POOL,
        'BSFAS' => self::FIELD_SCIENCE_POOL,
        'BACOMM' => ['writing', 'ui', 'web', 'data'],
        'default' => ['data', 'writing', 'ui', 'gis', 'support', 'database'],
    ];

    private function partnerSkills(): void
    {
        $skillIds = Skill::query()->pluck('id', 'name');
        $partners = IndustryPartner::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'like', '%@demo.gias.test'))
            ->get()
            ->keyBy('company_name');

        foreach (self::PARTNER_SKILLS as $company => $needs) {
            $partners->get($company)?->skills()->sync(
                collect($needs)->mapWithKeys(fn (int $importance, string $code) => [
                    $skillIds[self::SKILL[$code]] => ['importance' => $importance],
                ])->all(),
            );
        }
    }

    /**
     * 2–5 skills per student from their program's pool, with levels 1–3 (deterministic).
     *
     * @param  list<Student>  $students
     */
    private function studentSkills(array $students): void
    {
        $skillIds = Skill::query()->pluck('id', 'name');

        foreach ($students as $i => $student) {
            $code = $student->user->program?->code;
            $pool = self::STUDENT_SKILL_POOLS[$code] ?? self::STUDENT_SKILL_POOLS['default'];
            $count = 2 + ($i % 4);

            $picked = [];
            for ($j = 0; $j < min($count, count($pool)); $j++) {
                $skill = $pool[($i * 2 + $j) % count($pool)]; // consecutive, so always distinct
                $picked[$skill] = 1 + (($i + $j) % 3);
            }

            $student->skills()->sync(
                collect($picked)->mapWithKeys(fn (int $level, string $skill) => [
                    $skillIds[self::SKILL[$skill]] => ['level' => $level],
                ])->all(),
            );
        }
    }

    /**
     * One active coordinator per program, unless a real one is already active there.
     *
     * @return array<int, int> program id => coordinator user id
     */
    private function coordinators(Collection $programs): array
    {
        $byProgram = [];

        foreach ($programs->values() as $i => $program) {
            $email = 'demo.coordinator.'.strtolower($program->code).'@dnsc.edu.ph';

            $existing = User::query()
                ->where('role', UserRole::Coordinator)
                ->where('status', UserStatus::Active)
                ->where('program_id', $program->id)
                ->where('email', '!=', $email)
                ->first();

            if ($existing) { // keep the one-active-coordinator-per-program rule
                $byProgram[$program->id] = $existing->id;

                continue;
            }

            $byProgram[$program->id] = $this->user($email, [
                'name' => self::COORDINATOR_NAMES[$i % count(self::COORDINATOR_NAMES)],
                'role' => UserRole::Coordinator,
                'status' => UserStatus::Active,
                'program_id' => $program->id,
            ])->id;
        }

        return $byProgram;
    }

    /**
     * @return list<array{profile: IndustryPartner, interns: int}>
     */
    private function partners(): array
    {
        $result = [];
        $n = 0;

        foreach (self::PARTNERS as [$company, $city, $province, $lat, $lng, $slots, $interns]) {
            $profile = $this->partner(++$n, UserStatus::Active, $company, $city, $province, $slots, $lat, $lng);
            $result[] = ['profile' => $profile, 'interns' => $interns];
        }

        foreach (self::PENDING_PARTNERS as [$company, $city, $province, $lat, $lng, $slots]) {
            $this->partner(++$n, UserStatus::Pending, $company, $city, $province, $slots, $lat, $lng);
        }

        [$company, $city, $province, $slots] = self::UNLOCATED_PARTNER;
        $this->partner(++$n, UserStatus::Active, $company, $city, $province, $slots, null, null);

        return $result;
    }

    private function partner(
        int $n, UserStatus $status, string $company, string $city, Province $province, int $slots, ?float $lat, ?float $lng,
    ): IndustryPartner {
        $contact = self::CONTACT_NAMES[$n % count(self::CONTACT_NAMES)];

        $user = $this->user(sprintf('partner%02d@demo.gias.test', $n), [
            'name' => $contact,
            'role' => UserRole::Partner,
            'status' => $status,
            'program_id' => null,
        ]);

        return IndustryPartner::updateOrCreate(['user_id' => $user->id], [
            'company_name' => $company,
            'contact_person' => $contact,
            'contact_number' => sprintf('0917%07d', 1000000 + $n * 7919),
            'address' => 'Poblacion',
            'city' => $city,
            'province' => $province,
            'intern_slots' => $slots,
            'location' => $lat !== null ? Point::makeGeodetic($lat, $lng) : null,
            'geocode_status' => $lat !== null ? GeocodeStatus::Manual : GeocodeStatus::Failed,
        ]);
    }

    /**
     * @return list<Student>
     */
    private function students(Collection $programs): array
    {
        $students = [];
        $programList = $programs->values();

        for ($i = 0; $i < self::STUDENT_COUNT; $i++) {
            [$city, $province, $lat, $lng] = self::TOWNS[$i % count(self::TOWNS)];
            $hasHome = $i < self::STUDENT_COUNT - self::STUDENTS_WITHOUT_HOME;
            $first = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[intdiv($i, count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];
            $jitterLat = mt_rand(-150, 150) / 10000; // ±0.015° ≈ ±1.7 km
            $jitterLng = mt_rand(-150, 150) / 10000;

            $user = $this->user(sprintf('student%02d@demo.gias.test', $i + 1), [
                'name' => "{$first} {$last}",
                'role' => UserRole::Student,
                'status' => UserStatus::Active,
                'program_id' => $programList[$i % $programList->count()]->id,
            ]);

            $students[] = Student::updateOrCreate(['user_id' => $user->id], [
                'student_number' => sprintf('DEMO-2026-%04d', $i + 1),
                'year_level' => 3 + ($i % 2),
                'contact_number' => sprintf('0918%07d', ($i * 104729 + 2000000) % 10000000),
                'address' => 'Purok '.(1 + $i % 7),
                'city' => $city,
                'province' => $province,
                'home_location' => $hasHome ? Point::makeGeodetic($lat + $jitterLat, $lng + $jitterLng) : null,
                'geocode_status' => $hasHome ? GeocodeStatus::Located : GeocodeStatus::Failed,
            ]);
        }

        return $students;
    }

    /**
     * Fill partners in PARTNERS order with the first students; the rest stay unplaced.
     *
     * @param  list<Student>  $students
     * @param  list<array{profile: IndustryPartner, interns: int}>  $partners
     * @param  array<int, int>  $coordinators
     */
    /**
     * @return list<Deployment> in student order (index = student index)
     */
    private function deployments(array $students, array $partners, array $coordinators): array
    {
        $next = 0;
        $deployments = [];

        foreach ($partners as ['profile' => $partner, 'interns' => $interns]) {
            for ($k = 0; $k < min($interns, $partner->intern_slots); $k++) {
                $index = $next++;
                $student = $students[$index];
                $assignedBy = $coordinators[$student->user->program_id] ?? null;

                if ($index === self::COMPLETED_STUDENT) {
                    // Finished an earlier (summer) term, so it has enough signed hours.
                    $deployments[] = Deployment::updateOrCreate(
                        ['student_id' => $student->id, 'industry_partner_id' => $partner->id],
                        [
                            'status' => DeploymentStatus::Completed,
                            'start_date' => self::COMPLETED_START_DATE,
                            'end_date' => self::COMPLETED_END_DATE,
                            'assigned_by' => $assignedBy,
                            'completed_at' => self::COMPLETED_END_DATE.' 17:00:00',
                            'completed_by' => $assignedBy,
                        ],
                    );

                    continue;
                }

                $deployments[] = Deployment::updateOrCreate(
                    ['student_id' => $student->id, 'status' => DeploymentStatus::Active],
                    [
                        'industry_partner_id' => $partner->id,
                        'start_date' => self::START_DATE,
                        'end_date' => null,
                        'assigned_by' => $assignedBy,
                    ],
                );
            }
        }

        // Unplaced students: make sure no stale active deployment survives a re-run.
        foreach (array_slice($students, $next) as $student) {
            Deployment::query()
                ->where('student_id', $student->id)
                ->where('status', DeploymentStatus::Active)
                ->delete();
        }

        return $deployments;
    }

    // Weekly reports (Module 6)

    /** Student index whose deployment is completed (≥ 486 signed hours). */
    private const COMPLETED_STUDENT = 0;

    private const COMPLETED_START_DATE = '2026-06-02';

    private const COMPLETED_END_DATE = '2026-08-31'; // end of week 13

    /** Student indexes (among the deployed) with a special reporting state. */
    private const AWAITING_SIGNATURE = [1, 2, 3, 4];

    private const RETURNED = [5, 6];

    private const OVERDUE = [7, 8, 9];

    private const DAY_PATTERNS = [
        [8, 8, 7.5, 8, 7],
        [8, 7.5, 8, 8, 8],
        [7, 8, 8, 7.5, 8],
        [8, 8, 8, 7, 7.5],
    ];

    private const TASKS = [
        'Encoded field survey data into the office database and cleaned duplicate records.',
        'Assisted the IT staff with network troubleshooting and printer setup across departments.',
        'Prepared weekly summary reports and charts for the supervisor’s Monday meeting.',
        'Updated the office website announcements and fixed layout issues on mobile.',
        'Joined site visits, took notes and drafted the trip report with photos.',
        'Digitized paper forms into spreadsheets and checked them against the originals.',
        'Helped staff with computer problems and logged each ticket and its fix.',
        'Mapped client locations and prepared a simple map for the planning team.',
    ];

    private const LEARNINGS = [
        'Learned how the office keeps records and why consistent naming matters.',
        'Practiced explaining technical fixes to non-technical staff.',
        'Saw how data from the field turns into decisions in planning meetings.',
        null,
    ];

    private const RETURN_NOTE = 'Please list the specific tasks you did each day instead of a one-line summary, then resubmit.';

    /**
     * Weekly reports for the demo deployments, using the same week-period rule as the API.
     * "L" is the last week that has fully ended (local calendar):
     *  - most: weeks 1..L−1 signed (latest ends 8–14 days ago, so on track)
     *  - AWAITING_SIGNATURE: 1..L−1 signed, week L submitted
     *  - RETURNED: 1..L−1 signed, week L returned with a note
     *  - OVERDUE: only 1..L−3 signed (latest ends 22–28 days ago)
     *  - COMPLETED_STUDENT: weeks 1..13 signed at 40 h (520 h ≥ 486)
     *
     * @param  list<Deployment>  $deployments
     */
    private function weeklyReports(array $deployments): void
    {
        $today = WeeklyReportService::today();

        foreach ($deployments as $i => $deployment) {
            $partnerUserId = $deployment->industryPartner->user_id;

            if ($deployment->status === DeploymentStatus::Completed) {
                $plan = array_fill_keys(range(1, 13), WeeklyReportStatus::Signed);
            } else {
                $lastEnded = 0;
                while (WeeklyReportService::periodFor($deployment->start_date, $lastEnded + 1)[1]->toDateString() < $today) {
                    $lastEnded++;
                }

                $signedThrough = in_array($i, self::OVERDUE, true) ? $lastEnded - 3 : $lastEnded - 1;
                $plan = $signedThrough > 0 ? array_fill_keys(range(1, $signedThrough), WeeklyReportStatus::Signed) : [];

                if (in_array($i, self::AWAITING_SIGNATURE, true)) {
                    $plan[$lastEnded] = WeeklyReportStatus::Submitted;
                } elseif (in_array($i, self::RETURNED, true)) {
                    $plan[$lastEnded] = WeeklyReportStatus::Returned;
                }
            }

            foreach ($plan as $week => $status) {
                [$start, $end] = WeeklyReportService::periodFor($deployment->start_date, $week);
                $hours = $this->demoHours($i, $week, $deployment->status === DeploymentStatus::Completed);

                WeeklyReport::updateOrCreate(
                    ['deployment_id' => $deployment->id, 'week_number' => $week],
                    [
                        'period_start' => $start,
                        'period_end' => $end,
                        'daily_hours' => $hours,
                        'total_hours' => array_sum($hours),
                        'tasks' => self::TASKS[($i + $week) % count(self::TASKS)],
                        'learnings' => self::LEARNINGS[($i + $week) % count(self::LEARNINGS)],
                        'status' => $status,
                        'submitted_at' => $end->addDay()->setTime(17, 0),
                        'signed_by' => $status === WeeklyReportStatus::Signed ? $partnerUserId : null,
                        'signed_at' => $status === WeeklyReportStatus::Signed ? $end->addDays(2)->setTime(10, 0) : null,
                        'return_note' => $status === WeeklyReportStatus::Returned ? self::RETURN_NOTE : null,
                    ],
                );
            }

            // Re-runs on a later day: drop weeks the plan no longer has.
            WeeklyReport::query()
                ->where('deployment_id', $deployment->id)
                ->whereNotIn('week_number', array_keys($plan) ?: [0])
                ->delete();
        }
    }

    /**
     * Realistic 7–8 h weekdays, a Saturday half-day every fourth week, Sundays off.
     *
     * @return array<string, float>
     */
    private function demoHours(int $index, int $week, bool $fullWeeks): array
    {
        // Interns who need an open FINAL evaluation (≥ 90% of 486 h) work Saturday mornings too.
        if (in_array($index, self::FINAL_READY, true)) {
            return ['mon' => 8.0, 'tue' => 8.0, 'wed' => 8.0, 'thu' => 8.0, 'fri' => 8.0, 'sat' => 4.5, 'sun' => 0.0];
        }

        $weekdays = $fullWeeks ? [8, 8, 8, 8, 8] : self::DAY_PATTERNS[($index + $week) % count(self::DAY_PATTERNS)];

        return [
            'mon' => $weekdays[0], 'tue' => $weekdays[1], 'wed' => $weekdays[2], 'thu' => $weekdays[3], 'fri' => $weekdays[4],
            'sat' => ! $fullWeeks && ($index + $week) % 4 === 0 ? 4.0 : 0.0,
            'sun' => 0.0,
        ];
    }

    // Evaluations (Module 7)

    /** Final evaluation states (student indexes); these interns log 44.5 h weeks (445 h ≥ 437.4). */
    private const FINAL_SUBMITTED = [11, 12, 13];

    private const FINAL_DRAFT = [14, 15];

    private const FINAL_RETURNED = [16];

    private const FINAL_READY = [11, 12, 13, 14, 15, 16];

    /** Past 50% but the partner hasn't started the midterm yet. */
    private const MIDTERM_NOT_STARTED = [17, 18];

    private const EVALUATION_COMMENTS = [
        'Reliable and quick to learn our systems. Asks good questions.',
        'Works carefully and finishes tasks on time. Could speak up more in meetings.',
        'Shows initiative; took over the weekly report without being asked.',
        'Good attitude. Needs more practice explaining technical issues to staff.',
    ];

    private const EVALUATION_RETURN_NOTE = 'Please add a comment explaining the low Communication rating before I review it.';

    /**
     * Midterms reviewed for most deployments past 50%; finals in each state; the completed
     * deployment has both reviewed. Seeded only where the type is open (same rule as the API).
     *
     * @param  list<Deployment>  $deployments
     */
    private function evaluations(array $deployments): void
    {
        $service = app(EvaluationService::class);
        $criteria = EvaluationService::criteria();
        $snapshot = collect($criteria)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all();

        foreach ($deployments as $i => $deployment) {
            $deployment = $deployment->fresh(); // signed hours from the reports just seeded
            $plan = [];

            if (! in_array($i, self::MIDTERM_NOT_STARTED, true) && $service->openState($deployment, EvaluationType::Midterm)['open']) {
                $plan['midterm'] = EvaluationStatus::Reviewed;
            }
            if ($deployment->status === DeploymentStatus::Completed) {
                $plan['final'] = EvaluationStatus::Reviewed;
            } elseif ($service->openState($deployment, EvaluationType::Final)['open']) {
                $plan['final'] = match (true) {
                    in_array($i, self::FINAL_SUBMITTED, true) => EvaluationStatus::Submitted,
                    in_array($i, self::FINAL_DRAFT, true) => EvaluationStatus::Draft,
                    in_array($i, self::FINAL_RETURNED, true) => EvaluationStatus::Returned,
                    default => null,
                };
            }

            foreach (array_filter($plan) as $type => $status) {
                $ratings = $this->demoRatings($i, $type);
                if ($status === EvaluationStatus::Draft) {
                    $ratings = array_slice($ratings, 0, 3 + $i % 3, true); // partly rated
                }
                $complete = $status !== EvaluationStatus::Draft;
                $mean = $complete ? round(array_sum($ratings) / count($ratings), 2) : null;
                $reviewed = $status === EvaluationStatus::Reviewed;
                $coordinatorRating = $reviewed ? 3 + ($i % 3) : null;
                $when = $type === 'midterm'
                    ? $deployment->start_date->toImmutable()->addWeeks(6)
                    : now()->toImmutable()->subDays(2);

                Evaluation::updateOrCreate(
                    ['deployment_id' => $deployment->id, 'type' => $type],
                    [
                        'ratings' => $ratings,
                        'criteria_snapshot' => $complete ? $snapshot : null,
                        'partner_mean' => $mean,
                        'comment' => $i % 2 === 0 ? self::EVALUATION_COMMENTS[$i % count(self::EVALUATION_COMMENTS)] : null,
                        'status' => $status,
                        'submitted_by' => $complete ? $deployment->industryPartner->user_id : null,
                        'submitted_at' => $complete ? $when : null,
                        'coordinator_rating' => $coordinatorRating,
                        'coordinator_note' => $reviewed && $i % 3 === 0 ? 'Weekly reports complete and on time.' : null,
                        'final_rating' => $reviewed ? EvaluationService::finalRating($mean, $coordinatorRating) : null,
                        'reviewed_by' => $reviewed ? $deployment->assigned_by : null,
                        'reviewed_at' => $reviewed ? $when->addDays(3) : null,
                        'return_note' => $status === EvaluationStatus::Returned ? self::EVALUATION_RETURN_NOTE : null,
                    ],
                );
            }

            Evaluation::query()
                ->where('deployment_id', $deployment->id)
                ->whereNotIn('type', array_keys(array_filter($plan)) ?: ['none'])
                ->delete();
        }
    }

    /**
     * Mostly 3–5 with an occasional 2 (deterministic).
     *
     * @return array<string, int>
     */
    private function demoRatings(int $index, string $type): array
    {
        $ratings = [];
        foreach (array_keys(EvaluationService::criteria()) as $k => $key) {
            $value = 3 + (($index * 7 + $k * 3 + ($type === 'final' ? 1 : 0)) % 3);
            $ratings[$key] = ($index + $k) % 11 === 0 ? 2 : $value;
        }

        return $ratings;
    }

    // Analytics history, algorithm data and feedback (Module 9)

    /**
     * Completed deployments in the 4 terms before START_DATE's term, oldest first. The last of
     * those terms (Midyear) also holds COMPLETED_STUDENT's deployment, so it shows 78.
     */
    private const HISTORY = [48, 55, 63, 77];

    private const ALUMNI_EMAIL = 'alumni%03d@demo.gias.test';

    /**
     * When an algorithm wasn't the one used, the chance (%) that it still ranked the chosen
     * partner 1st. Different per algorithm so the comparison shows a spread.
     */
    private const RANK1_CHANCE = ['cos' => 30, 'wsm' => 55, 'fuzzy' => 45];

    private const FEEDBACK_COUNT = 25;

    /** Coordinator ratings (1–5) cycled per algorithm. */
    private const FEEDBACK_RATINGS = [
        'cos' => [3, 4, 3, 2, 4, 3, 3, 4, 3],
        'wsm' => [5, 4, 4, 5, 4, 5, 4, 4, 5],
        'fuzzy' => [4, 4, 3, 5, 4, 3, 4, 4, 4],
    ];

    /**
     * Graduated (inactive) students with completed, signed and evaluated deployments in earlier
     * terms, so the trend and past-term reports have history. Their deployments are rebuilt on
     * every run.
     *
     * @param  list<array{profile: IndustryPartner, interns: int}>  $partners
     * @param  array<int, int>  $coordinators
     */
    private function history(Collection $programs, array $partners, array $coordinators): void
    {
        $terms = array_reverse(array_slice(AcademicTerm::list(count(self::HISTORY) + 1, AcademicTerm::forDate(self::START_DATE)), 1));
        $hosts = array_values(array_filter(array_column($partners, 'profile'), fn (IndustryPartner $p) => $p->location !== null));
        $programList = $programs->values();
        $criteria = EvaluationService::criteria();
        $snapshot = json_encode(collect($criteria)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all());
        $now = now();
        $reports = [];
        $evaluations = [];
        $n = 0;

        foreach ($terms as $t => $term) {
            // One entry per slot, shuffled: a term never puts more interns at a partner than it has slots.
            $pool = [];
            foreach ($hosts as $host) {
                array_push($pool, ...array_fill(0, $host->intern_slots, $host));
            }
            for ($i = count($pool) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
            }

            for ($k = 0; $k < self::HISTORY[$t]; $k++, $n++) {
                [$city, $province, $lat, $lng] = self::TOWNS[$n % count(self::TOWNS)];
                $program = $programList[($n * 7) % $programList->count()];
                $user = $this->user(sprintf(self::ALUMNI_EMAIL, $n + 1), [
                    'name' => self::FIRST_NAMES[($n * 7) % count(self::FIRST_NAMES)].' '.self::LAST_NAMES[($n * 3 + $t) % count(self::LAST_NAMES)],
                    'role' => UserRole::Student,
                    'status' => UserStatus::Inactive, // graduated
                    'program_id' => $program->id,
                ]);
                $student = Student::updateOrCreate(['user_id' => $user->id], [
                    'student_number' => sprintf('DEMO-ALUM-%04d', $n + 1),
                    'year_level' => 4,
                    'contact_number' => sprintf('0917%07d', ($n * 7919 + 1000000) % 10000000),
                    'address' => 'Purok '.(1 + $n % 7),
                    'city' => $city,
                    'province' => $province,
                    'home_location' => Point::makeGeodetic($lat + mt_rand(-150, 150) / 10000, $lng + mt_rand(-150, 150) / 10000),
                    'geocode_status' => GeocodeStatus::Located,
                ]);
                $student->deployments()->delete(); // reports and evaluations cascade

                $host = $pool[$k % count($pool)];
                $start = $term->start->addWeeks($k % 3);
                $end = $start->addWeeks(13)->subDay();
                $coordinatorId = $coordinators[$program->id] ?? null;
                [$algorithm, $ranks] = $this->algorithmChoice();

                $deployment = Deployment::create([
                    'student_id' => $student->id,
                    'industry_partner_id' => $host->id,
                    'status' => DeploymentStatus::Completed,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'assigned_by' => $coordinatorId,
                    'algorithm_used' => $algorithm,
                    'rank_cos' => $ranks['cos'],
                    'rank_wsm' => $ranks['wsm'],
                    'rank_fuzzy' => $ranks['fuzzy'],
                    'completed_at' => $end->setTime(17, 0),
                    'completed_by' => $coordinatorId,
                ]);

                for ($week = 1; $week <= 13; $week++) {
                    [$from, $to] = WeeklyReportService::periodFor($start, $week);
                    $days = self::DAY_PATTERNS[($n + $week) % count(self::DAY_PATTERNS)];
                    $reports[] = [
                        'deployment_id' => $deployment->id,
                        'week_number' => $week,
                        'period_start' => $from->toDateString(),
                        'period_end' => $to->toDateString(),
                        'daily_hours' => json_encode(array_combine(['mon', 'tue', 'wed', 'thu', 'fri'], $days) + ['sat' => 0, 'sun' => 0]),
                        'total_hours' => array_sum($days),
                        'tasks' => self::TASKS[($n + $week) % count(self::TASKS)],
                        'learnings' => null,
                        'status' => WeeklyReportStatus::Signed->value,
                        'submitted_at' => $to->addDay()->setTime(9, 0),
                        'signed_by' => $host->user_id,
                        'signed_at' => $to->addDays(2)->setTime(15, 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $ratings = $this->demoRatings($n + 100, 'final');
                $mean = round(array_sum($ratings) / count($ratings), 2);
                $coordinatorRating = 3 + ($n % 3);
                $evaluations[] = [
                    'deployment_id' => $deployment->id,
                    'type' => EvaluationType::Final->value,
                    'ratings' => json_encode($ratings),
                    'criteria_snapshot' => $snapshot,
                    'partner_mean' => $mean,
                    'comment' => null,
                    'status' => EvaluationStatus::Reviewed->value,
                    'submitted_by' => $host->user_id,
                    'submitted_at' => $end->subDays(3)->setTime(10, 0),
                    'coordinator_rating' => $coordinatorRating,
                    'coordinator_note' => null,
                    'final_rating' => EvaluationService::finalRating($mean, $coordinatorRating),
                    'reviewed_by' => $coordinatorId,
                    'reviewed_at' => $end->setTime(16, 0),
                    'return_note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($reports, 500) as $chunk) {
            DB::table('weekly_reports')->insert($chunk);
        }
        foreach (array_chunk($evaluations, 500) as $chunk) {
            DB::table('evaluations')->insert($chunk);
        }
    }

    /**
     * Which algorithm the coordinator followed (or manual) and where each algorithm ranked the
     * chosen partner.
     *
     * @return array{0: string, 1: array{cos: int, wsm: int, fuzzy: int}}
     */
    private function algorithmChoice(): array
    {
        $roll = mt_rand(1, 100);
        $used = match (true) {
            $roll <= 35 => 'wsm',
            $roll <= 60 => 'fuzzy',
            $roll <= 82 => 'cos',
            default => 'manual',
        };

        $ranks = [];
        foreach (self::RANK1_CHANCE as $algorithm => $chance) {
            $r = mt_rand(1, 100);
            $ranks[$algorithm] = $algorithm === $used
                ? ($r <= 75 ? 1 : ($r <= 92 ? 2 : 3))
                : ($r <= $chance ? 1 : ($r <= $chance + 25 ? mt_rand(2, 3) : mt_rand(4, 9)));
        }

        return [$used, $ranks];
    }

    /**
     * @param  list<Deployment>  $deployments
     */
    private function algorithmData(array $deployments): void
    {
        foreach ($deployments as $deployment) {
            [$algorithm, $ranks] = $this->algorithmChoice();
            $deployment->update([
                'algorithm_used' => $algorithm,
                'rank_cos' => $ranks['cos'],
                'rank_wsm' => $ranks['wsm'],
                'rank_fuzzy' => $ranks['fuzzy'],
            ]);
        }
    }

    /**
     * Coordinators' 1–5 ratings of each algorithm's list, given in the current term before the
     * placements. Replaces any feedback on demo students.
     *
     * @param  list<Student>  $students
     * @param  array<int, int>  $coordinators
     */
    private function feedback(array $students, array $coordinators): void
    {
        RecommendationFeedback::query()->whereIn('student_id', array_map(fn (Student $s) => $s->id, $students))->delete();
        $algorithms = array_keys(self::FEEDBACK_RATINGS);
        $ratedOn = CarbonImmutable::parse(self::START_DATE)->subDays(20);

        for ($i = 0; $i < self::FEEDBACK_COUNT; $i++) {
            $student = $students[($i * 7) % 44];
            $algorithm = $algorithms[$i % 3];
            $at = $ratedOn->addDays($i)->setTime(10, 0);
            $feedback = RecommendationFeedback::create([
                'student_id' => $student->id,
                'algorithm' => $algorithm,
                'rating' => self::FEEDBACK_RATINGS[$algorithm][intdiv($i, 3) % count(self::FEEDBACK_RATINGS[$algorithm])],
                'comment' => $i % 4 === 0 ? 'Top picks matched the student’s skills and commute.' : null,
                'rated_by' => $coordinators[$student->user->program_id] ?? null,
                'snapshot' => ['algorithm' => $algorithm, 'seeded' => true],
            ]);
            $feedback->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(string $email, array $attributes): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill($attributes + [
            'password' => $this->passwordHash,
            'email_verified_at' => now(),
            'review_note' => null,
        ])->save();

        return $user;
    }
}
