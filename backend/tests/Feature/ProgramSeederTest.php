<?php

namespace Tests\Feature;

use App\Models\Program;
use Database\Seeders\ProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_dnsc_programs_and_can_run_twice(): void
    {
        Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']); // older short name

        $this->seed(ProgramSeeder::class);
        $this->seed(ProgramSeeder::class);

        $this->assertSame(20, Program::count());
        $this->assertSame(
            'Bachelor of Science in Information Technology',
            Program::where('code', 'BSIT')->value('name'), // updated, not duplicated
        );
        $this->assertSame(
            'Bachelor of Public Administration - Special Program for Adult Education',
            Program::where('code', 'BPASPA')->value('name'),
        );
    }
}
