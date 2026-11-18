<?php

namespace App\Services;

use App\Models\Program;
use Illuminate\Database\Eloquent\Collection;

class ProgramService
{
    /**
     * @return Collection<int, Program>
     */
    public function all(): Collection
    {
        return Program::query()->orderBy('code')->get();
    }

    /**
     * Admin: hours a student must have countersigned to complete OJT (validated 100–2000).
     */
    public function updateRequiredHours(Program $program, int $hours): Program
    {
        $program->update(['required_ojt_hours' => $hours]);

        return $program;
    }
}
