<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Http\Resources\AnalyticsOverviewResource;
use App\Services\AnalyticsService;

/**
 * Admin (all, or one program) and coordinator (own program).
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function overview(AnalyticsFilterRequest $request): AnalyticsOverviewResource
    {
        return new AnalyticsOverviewResource($this->analyticsService->overview($request->user(), $request->filters()));
    }
}
