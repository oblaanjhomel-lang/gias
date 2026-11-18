<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeploymentProgressResource;
use App\Services\DeploymentMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Active and completed interns at the signed-in partner's site, with progress.
 */
class PartnerInternController extends Controller
{
    public function __construct(private readonly DeploymentMonitoringService $monitoringService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeploymentProgressResource::collection($this->monitoringService->partnerInterns($request->user()));
    }
}
