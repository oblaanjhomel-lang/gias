<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Enums\EvaluationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'deployment_id', 'type', 'ratings', 'criteria_snapshot', 'partner_mean', 'comment', 'status',
    'submitted_by', 'submitted_at', 'coordinator_rating', 'coordinator_note', 'final_rating',
    'reviewed_by', 'reviewed_at', 'return_note',
])]
class Evaluation extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'ratings' => '{}',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EvaluationType::class,
            'status' => EvaluationStatus::class,
            'ratings' => 'array',
            'criteria_snapshot' => 'array',
            'partner_mean' => 'decimal:2',
            'final_rating' => 'decimal:2',
            'coordinator_rating' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Deployment, $this>
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
