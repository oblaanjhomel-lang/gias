<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluationExportRequest;
use App\Services\EvaluationReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin CSV: ?type=midterm|final.
 */
class EvaluationExportController extends Controller
{
    public function __construct(private readonly EvaluationReportService $reportService) {}

    public function __invoke(EvaluationExportRequest $request): StreamedResponse
    {
        return $this->reportService->exportCsv($request->evaluationType());
    }
}
