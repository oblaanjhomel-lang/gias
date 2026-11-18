<?php

namespace App\Models;

use App\Enums\WeeklyReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'deployment_id', 'week_number', 'period_start', 'period_end', 'daily_hours', 'total_hours',
    'tasks', 'learnings', 'status', 'submitted_at', 'signed_by', 'signed_at', 'return_note',
])]
class WeeklyReport extends Model
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_number' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'daily_hours' => 'array',
            'total_hours' => 'decimal:2',
            'status' => WeeklyReportStatus::class,
            'submitted_at' => 'datetime',
            'signed_at' => 'datetime',
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
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
