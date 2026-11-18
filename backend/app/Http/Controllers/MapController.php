<?php

namespace App\Http\Controllers;

use App\Http\Requests\MapPartnersRequest;
use App\Http\Requests\MapSummaryRequest;
use App\Http\Requests\UpdatePartnerLocationRequest;
use App\Http\Resources\NearbyPartnerResource;
use App\Http\Resources\UnlocatedPartnerResource;
use App\Models\User;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GeoJSON, summary and config are built by MapService and returned as-is.
 */
class MapController extends Controller
{
    public function __construct(private readonly MapService $mapService) {}

    public function config(): JsonResponse
    {
        return response()->json($this->mapService->config());
    }

    public function partners(MapPartnersRequest $request): JsonResponse
    {
        return response()->json($this->mapService->partnersGeoJson($request->user(), $request->validated()));
    }

    public function unlocated(): AnonymousResourceCollection
    {
        return UnlocatedPartnerResource::collection($this->mapService->unlocated());
    }

    public function updateLocation(UpdatePartnerLocationRequest $request, User $partner): JsonResponse
    {
        return response()->json($this->mapService->updatePartnerLocation(
            $request->user(),
            $partner,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        ));
    }

    public function nearby(Request $request): AnonymousResourceCollection
    {
        $result = $this->mapService->nearbyForStudent($request->user());

        return NearbyPartnerResource::collection($result['partners'])->additional([
            'meta' => ['home_missing' => $result['home_missing'], 'home' => $result['home']],
        ]);
    }

    public function summary(MapSummaryRequest $request): JsonResponse
    {
        return response()->json($this->mapService->summary($request->user(), $request->radiusKm()));
    }
}
