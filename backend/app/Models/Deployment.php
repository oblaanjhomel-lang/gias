<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A student's OJT placement at an Industry Partner. At most one `active` per student
 * (partial unique index). Placement UI comes in Module 5.
 */
#[Fillable([
    'student_id', 'industry_partner_id', 'status', 'start_date', 'end_date', 'assigned_by',
    'algorithm_used', 'rank_cos', 'rank_wsm', 'rank_fuzzy', 'recommendation_snapshot',
    'completed_at', 'completed_by', 'completion_note',
])]
class Deployment extends Model
{
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeploymentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'rank_cos' => 'integer',
            'rank_wsm' => 'integer',
            'rank_fuzzy' => 'integer',
            'recommendation_snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<WeeklyReport, $this>
     */
    public function weeklyReports(): HasMany
    {
        return $this->hasMany(WeeklyReport::class);
    }

    /**
     * @return HasMany<Evaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * @return HasOne<Evaluation, $this>
     */
    public function midterm(): HasOne
    {
        return $this->hasOne(Evaluation::class)->where('type', EvaluationType::Midterm);
    }

    /**
     * @return HasOne<Evaluation, $this>
     */
    public function final(): HasOne
    {
        return $this->hasOne(Evaluation::class)->where('type', EvaluationType::Final);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<IndustryPartner, $this>
     */
    public function industryPartner(): BelongsTo
    {
        return $this->belongsTo(IndustryPartner::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
