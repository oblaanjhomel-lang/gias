<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public const SKILLS = [
        'Web development (PHP/Laravel)',
        'Frontend (Vue/React)',
        'Database & SQL',
        'Networking',
        'Technical support',
        'Hardware troubleshooting',
        'GIS & mapping',
        'Data analysis',
        'Graphic & UI design',
        'Mobile development',
        'Cybersecurity basics',
        'Technical writing',
    ];

    public function run(): void
    {
        foreach (self::SKILLS as $name) {
            Skill::firstOrCreate(['name' => $name]); // keeps an admin's is_active choice on re-run
        }
    }
}
