<?php

namespace App\Services;

use App\Support\AcademicTerm;

class TermService
{
    /**
     * @return array{current: array<string, string>, terms: list<array<string, string>>}
     */
    public function overview(): array
    {
        return [
            'current' => AcademicTerm::current()->toArray(),
            'terms' => array_map(fn (AcademicTerm $t) => $t->toArray(), AcademicTerm::list(8)),
        ];
    }
}
