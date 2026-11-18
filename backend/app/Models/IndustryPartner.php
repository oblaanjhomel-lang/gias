<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use App\Enums\GeocodeStatus;
use App\Enums\Province;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\IndustryPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_name', 'contact_person', 'contact_number', 'address', 'city', 'province',
    'location', 'geocode_status', 'intern_slots',
])]
class IndustryPartner extends Model
{
    /** @use HasFactory<IndustryPartnerFactory> */
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
            'location' => Point::class,
            'province' => Province::class,
            'geocode_status' => GeocodeStatus::class,
            'intern_slots' => 'integer',
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
     * @return HasMany<PartnerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function activeDeployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->where('status', DeploymentStatus::Active);
    }

    /**
     * Skills this partner needs (pivot importance: 1 preferred, 2 required).
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'partner_skill')->withPivot('importance');
    }
}
