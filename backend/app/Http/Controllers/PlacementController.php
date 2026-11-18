<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceBatchRequest;
use App\Http\Requests\PlaceStudentRequest;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use App\Services\PlacementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The only way a student gets placed: an explicit Coordinator / Admin action.
 */
class PlacementController extends Controller
{
    public function __construct(private readonly PlacementService $placementService) {}

    public function store(PlaceStudentRequest $request): DeploymentResource
    {
        return new DeploymentResource($this->placementService->placeByIds(
            $request->user(),
            (int) $request->validated('student_id'),
            (int) $request->validated('partner_id'),
            $request->validated('algorithm'),
            $request->validated('start_date'),
        ));
    }

    public function batch(PlaceBatchRequest $request): AnonymousResourceCollection
    {
        return DeploymentResource::collection(
            $this->placementService->placeBatch($request->user(), $request->validated('items')),
        );
    }

    public function withdraw(Request $request, Deployment $deployment): DeploymentResource
    {
        return new DeploymentResource($this->placementService->withdraw($request->user(), $deployment));
    }
}
