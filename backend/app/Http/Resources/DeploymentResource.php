<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deployment
 */
class DeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'student' => ['id' => $this->student->user_id, 'name' => $this->student->user->name],
            'partner' => ['id' => $this->industryPartner->user_id, 'company_name' => $this->industryPartner->company_name],
            'algorithm_used' => $this->algorithm_used,
            'rank_cos' => $this->rank_cos,
            'rank_wsm' => $this->rank_wsm,
            'rank_fuzzy' => $this->rank_fuzzy,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
        ];
    }
}
