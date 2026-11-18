<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePartnerSkillsRequest;
use App\Http\Resources\SkillSelectionResource;
use App\Services\SkillService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in partner's own needed skills.
 */
class PartnerSkillController extends Controller
{
    public function __construct(private readonly SkillService $skillService) {}

    public function show(Request $request): AnonymousResourceCollection
    {
        return SkillSelectionResource::collection($this->skillService->partnerSkills($request->user()));
    }

    public function update(UpdatePartnerSkillsRequest $request): AnonymousResourceCollection
    {
        return SkillSelectionResource::collection(
            $this->skillService->syncPartnerSkills($request->user(), $request->validated('skills')),
        );
    }
}
