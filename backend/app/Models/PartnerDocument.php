<?php

namespace App\Models;

use App\Enums\PartnerDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'original_name', 'path', 'size'])]
class PartnerDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartnerDocumentType::class,
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<IndustryPartner, $this>
     */
    public function industryPartner(): BelongsTo
    {
        return $this->belongsTo(IndustryPartner::class);
    }
}
