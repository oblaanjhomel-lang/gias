<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportRecommendationsRequest;
use App\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV for evaluating the recommenders: ?type=placements | feedback.
 */
class RecommendationExportController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function __invoke(ExportRecommendationsRequest $request): StreamedResponse
    {
        return $request->validated('type') === 'placements'
            ? $this->exportService->placements()
            : $this->exportService->feedback();
    }
}
