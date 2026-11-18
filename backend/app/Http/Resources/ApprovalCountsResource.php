<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * {students_pending, partners_pending}. Not wrapped in "data".
 */
class ApprovalCountsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{students_pending: int, partners_pending: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'students_pending' => (int) $this->resource['students_pending'],
            'partners_pending' => (int) $this->resource['partners_pending'],
        ];
    }
}
