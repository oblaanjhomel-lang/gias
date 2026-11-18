<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Partner view: a student who fits the partner's needs. Name and program ONLY: no email,
 * contact number, address or student number.
 */
class MatchingStudentResource extends JsonResource
{
    /**
     * @return array{name: string, program: string|null, score: float}
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'],
            'program' => $this->resource['program'],
            'score' => $this->resource['score'],
        ];
    }
}
