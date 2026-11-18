<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStudentSkillsRequest;
use App\Http\Resources\SkillSelectionResource;
use App\Services\SkillService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in student's own skills.
 */
class StudentSkillController extends Controller
{
    public function __construct(private readonly SkillService $skillService) {}

    public function show(Request $request): AnonymousResourceCollection
    {
        return SkillSelectionResource::collection($this->skillService->studentSkills($request->user()));
    }

    public function update(UpdateStudentSkillsRequest $request): AnonymousResourceCollection
    {
        return SkillSelectionResource::collection(
            $this->skillService->syncStudentSkills($request->user(), $request->validated('skills')),
        );
    }
}
