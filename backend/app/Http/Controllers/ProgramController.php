<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRequiredHoursRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Services\ProgramService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgramController extends Controller
{
    public function __construct(private readonly ProgramService $programService) {}

    /** GET /api/programs (public). */
    public function __invoke(): AnonymousResourceCollection
    {
        return ProgramResource::collection($this->programService->all());
    }

    /** PUT /api/admin/programs/{program}/required-hours (admin). */
    public function updateRequiredHours(UpdateRequiredHoursRequest $request, Program $program): ProgramResource
    {
        return new ProgramResource(
            $this->programService->updateRequiredHours($program, (int) $request->validated('required_ojt_hours')),
        );
    }
}
