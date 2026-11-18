<?php

namespace Tests\Concerns;

use App\Enums\DeploymentStatus;
use App\Enums\GeocodeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Deployment;
use App\Models\IndustryPartner;
use App\Models\Program;
use App\Models\Skill;
use App\Models\Student;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Seeders\SkillSeeder;

/**
 * Small, explicit fixtures for the recommendation / placement tests.
 */
trait BuildsRecommendationFixtures
{
    protected const TAGUM = [7.4478, 125.8078];

    protected const PANABO = [7.3081, 125.6841];

    protected const DAVAO = [7.0731, 125.6128];

    protected const SKILL_CODES = [
        'web' => 'Web development (PHP/Laravel)',
        'frontend' => 'Frontend (Vue/React)',
        'database' => 'Database & SQL',
        'networking' => 'Networking',
        'gis' => 'GIS & mapping',
        'data' => 'Data analysis',
        'ui' => 'Graphic & UI design',
        'writing' => 'Technical writing',
    ];

    protected User $admin;

    protected User $coordinator;

    protected Program $bsit;

    protected Program $bsab;

    protected function setUpFixtures(): void
    {
        $this->seed(SkillSeeder::class);
        $this->bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $this->bsab = Program::create(['code' => 'BSAB', 'name' => 'BS Agribusiness']);
        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->coordinator = User::factory()->role(UserRole::Coordinator)->create(['program_id' => $this->bsit->id]);
    }

    protected function skill(string $code): Skill
    {
        return Skill::where('name', self::SKILL_CODES[$code])->firstOrFail();
    }

    /**
     * @param  array<string, int>  $needs  code => importance
     */
    protected function partner(string $company, ?array $at, int $slots, array $needs = [], UserStatus $status = UserStatus::Active): IndustryPartner
    {
        $partner = IndustryPartner::factory()
            ->for(User::factory()->role(UserRole::Partner)->status($status))
            ->create([
                'company_name' => $company,
                'intern_slots' => $slots,
                'location' => $at ? Point::makeGeodetic($at[0], $at[1]) : null,
                'geocode_status' => $at ? GeocodeStatus::Manual : GeocodeStatus::Failed,
            ]);

        foreach ($needs as $code => $importance) {
            $partner->skills()->attach($this->skill($code)->id, ['importance' => $importance]);
        }

        return $partner;
    }

    /**
     * @param  array<string, int>  $skills  code => level
     */
    protected function student(Program $program, ?array $home, array $skills = [], UserStatus $status = UserStatus::Active, string $name = ''): Student
    {
        $student = Student::factory()
            ->for(User::factory()->role(UserRole::Student)->status($status)->state(
                ['program_id' => $program->id] + ($name !== '' ? ['name' => $name] : []),
            ))
            ->create(['home_location' => $home ? Point::makeGeodetic($home[0], $home[1]) : null]);

        foreach ($skills as $code => $level) {
            $student->skills()->attach($this->skill($code)->id, ['level' => $level]);
        }

        return $student;
    }

    protected function deploy(Student $student, IndustryPartner $partner, DeploymentStatus $status = DeploymentStatus::Active): Deployment
    {
        return Deployment::create([
            'student_id' => $student->id,
            'industry_partner_id' => $partner->id,
            'status' => $status,
            'start_date' => '2026-09-01',
        ]);
    }
}
