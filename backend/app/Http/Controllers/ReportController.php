<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSV (streamed) or PDF downloads. File responses, so no API Resource.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function __invoke(ReportRequest $request, string $type): Response
    {
        return $this->reportService->download($request->user(), $type, $request->validated('format'), $request->filters());
    }
}
