<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One skill a partner needs ({skill_id, name, importance}) or a student has ({skill_id, name, level}).
 * Wraps the arrays built by SkillService.
 */
class SkillSelectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip(['skill_id', 'name', 'importance', 'level']));
    }
}
