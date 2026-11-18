<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'required_ojt_hours'])]
class Program extends Model
{
    /** Matches the column default, so new models have it before a refresh. */
    protected $attributes = [
        'required_ojt_hours' => 486,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['required_ojt_hours' => 'integer'];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
