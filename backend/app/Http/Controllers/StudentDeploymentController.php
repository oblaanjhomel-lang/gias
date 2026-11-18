<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeploymentDetailResource;
use App\Services\DeploymentMonitoringService;
use Illuminate\Http\Request;

/**
 * The signed-in student's active (or latest) deployment. 404 with a message if never deployed.
 */
class StudentDeploymentController extends Controller
{
    public function __construct(private readonly DeploymentMonitoringService $monitoringService) {}

    public function show(Request $request): DeploymentDetailResource
    {
        return new DeploymentDetailResource($this->monitoringService->forStudent($request->user()));
    }
}
