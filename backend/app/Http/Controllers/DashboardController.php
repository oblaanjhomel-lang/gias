<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\Request;

/**
 * Any role; the payload depends on the role.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function __invoke(Request $request): DashboardResource
    {
        return new DashboardResource($this->dashboardService->for($request->user()));
    }
}
