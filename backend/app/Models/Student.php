<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use App\Enums\GeocodeStatus;
use App\Enums\Province;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'student_number', 'year_level', 'contact_number', 'address', 'city', 'province',
    'home_location', 'geocode_status',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $attributes = [
        'geocode_status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'province' => Province::class,
            'home_location' => Point::class,
            'geocode_status' => GeocodeStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasOne<Deployment, $this>
     */
    public function activeDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->where('status', DeploymentStatus::Active);
    }

    /**
     * Skills this student has (pivot level: 1 beginner, 2 intermediate, 3 advanced).
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'student_skill')->withPivot('level');
    }
}
