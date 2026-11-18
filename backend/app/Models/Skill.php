<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'is_active'])]
class Skill extends Model
{
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Partners that need this skill (pivot importance: 1 preferred, 2 required).
     *
     * @return BelongsToMany<IndustryPartner, $this>
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(IndustryPartner::class, 'partner_skill')->withPivot('importance');
    }

    /**
     * Students who have this skill (pivot level: 1–3).
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_skill')->withPivot('level');
    }
}
