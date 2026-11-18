<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSkillRequest;
use App\Http\Requests\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use App\Services\SkillService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SkillController extends Controller
{
    public function __construct(private readonly SkillService $skillService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SkillResource::collection($this->skillService->list($request->user()));
    }

    public function store(StoreSkillRequest $request): SkillResource
    {
        return new SkillResource($this->skillService->create($request->validated()));
    }

    public function update(UpdateSkillRequest $request, Skill $skill): SkillResource
    {
        return new SkillResource($this->skillService->update($skill, $request->validated()));
    }

    public function destroy(Skill $skill): Response
    {
        $this->skillService->delete($skill);

        return response()->noContent();
    }
}
