<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * DNSC's academic programs: default data for every installation (called from DatabaseSeeder).
 * Idempotent: matched on `code`, so re-running updates names and never duplicates.
 */
class ProgramSeeder extends Seeder
{
    public const PROGRAMS = [
        'BSMB' => 'Bachelor of Science in Marine Biology',
        'BSFT' => 'Bachelor of Science in Food Technology',
        'BSFAS' => 'Bachelor of Science in Fisheries and Aquatic Sciences',
        'BSAF' => 'Bachelor of Science in Agro-Forestry',
        'BSIS' => 'Bachelor of Science in Information Systems',
        'BSIT' => 'Bachelor of Science in Information Technology',
        'BACOMM' => 'Bachelor of Arts in Communication',
        'BSEDSCI' => 'Bachelor of Secondary Education Major in Science',
        'BTLED' => 'Bachelor of Technology and Livelihood Education',
        'BPED' => 'Bachelor of Physical Education',
        'BSEDENG' => 'Bachelor of Secondary Education Major in English',
        'BSEDMATH' => 'Bachelor of Secondary Education Major in Math',
        'BSTM' => 'Bachelor of Science in Tourism Management',
        'BSENTREPSPA' => 'Bachelor of Science in Entrepreneurship - Special Program for Adult Education',
        'BSENTREP' => 'Bachelor of Science in Entrepreneurship',
        'BSDRM' => 'Bachelor of Science in Disaster Resiliency and Management',
        'BSSW' => 'Bachelor of Science in Social Work',
        'BPA' => 'Bachelor of Public Administration',
        'BPASPA' => 'Bachelor of Public Administration - Special Program for Adult Education',
        'BSCRIM' => 'Bachelor of Science in Criminology',
    ];

    public function run(): void
    {
        foreach (self::PROGRAMS as $code => $name) {
            Program::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
