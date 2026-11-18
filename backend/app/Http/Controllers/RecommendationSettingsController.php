<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRecommendationSettingsRequest;
use App\Http\Resources\RecommendationSettingsResource;
use App\Services\RecommendationSettingsService;

class RecommendationSettingsController extends Controller
{
    public function __construct(private readonly RecommendationSettingsService $settingsService) {}

    public function show(): RecommendationSettingsResource
    {
        return new RecommendationSettingsResource($this->settingsService->current());
    }

    public function update(UpdateRecommendationSettingsRequest $request): RecommendationSettingsResource
    {
        return new RecommendationSettingsResource($this->settingsService->update($request->user(), $request->validated()));
    }
}
