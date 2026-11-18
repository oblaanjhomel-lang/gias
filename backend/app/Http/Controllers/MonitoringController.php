<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteDeploymentRequest;
use App\Http\Requests\MonitoringListRequest;
use App\Http\Resources\DeploymentDetailResource;
use App\Http\Resources\MonitoringRowResource;
use App\Models\Deployment;
use App\Services\DeploymentMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Coordinator (own program) / admin (all) deployment monitoring.
 */
class MonitoringController extends Controller
{
    public function __construct(private readonly DeploymentMonitoringService $monitoringService) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->monitoringService->summary($request->user())]);
    }

    public function index(MonitoringListRequest $request): AnonymousResourceCollection
    {
        $result = $this->monitoringService->list($request->user(), $request->validated());

        return MonitoringRowResource::collection($result['deployments'])->additional([
            'meta' => ['scope_total' => $result['scope_total']],
        ]);
    }

    public function show(Deployment $deployment): DeploymentDetailResource
    {
        return new DeploymentDetailResource($this->monitoringService->detail($deployment));
    }

    public function complete(CompleteDeploymentRequest $request, Deployment $deployment): DeploymentDetailResource
    {
        return new DeploymentDetailResource($this->monitoringService->complete(
            $request->user(),
            $deployment,
            $request->boolean('force'),
            $request->validated('note'),
        ));
    }
}
