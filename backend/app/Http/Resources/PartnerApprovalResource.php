<?php

namespace App\Http\Resources;

use App\Models\IndustryPartner;
use App\Models\PartnerDocument;
use Illuminate\Http\Request;

/**
 * An Industry Partner registration: Module 2's partner fields plus documents and review info.
 * Wraps an IndustryPartner with `user` and `documents` loaded.
 *
 * @mixin IndustryPartner
 */
class PartnerApprovalResource extends IndustryPartnerResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'documents' => $this->documents->map(fn (PartnerDocument $document) => [
                'id' => $document->id,
                'type' => $document->type->value,
                'original_name' => $document->original_name,
                'size' => $document->size,
            ])->values()->all(),
            'reviewed_at' => $this->user->reviewed_at?->toIso8601String(),
            'review_note' => $this->user->review_note,
        ];
    }
}
