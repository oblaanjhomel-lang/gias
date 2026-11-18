<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeeklyReportRequest;
use App\Http\Requests\UpdateWeeklyReportRequest;
use App\Http\Resources\WeeklyReportResource;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A student's own weekly reports ({report} routes pass `can:manage,report`).
 */
class StudentReportController extends Controller
{
    public function __construct(private readonly WeeklyReportService $reportService) {}

    /** Create the week's draft, or update it while it's still a draft / returned. */
    public function store(StoreWeeklyReportRequest $request): WeeklyReportResource
    {
        return new WeeklyReportResource($this->reportService->saveDraft($request->user(), $request->validated()));
    }

    public function update(UpdateWeeklyReportRequest $request, WeeklyReport $report): WeeklyReportResource
    {
        return new WeeklyReportResource($this->reportService->update($request->user(), $report, $request->validated()));
    }

    public function submit(Request $request, WeeklyReport $report): WeeklyReportResource
    {
        return new WeeklyReportResource($this->reportService->submit($request->user(), $report));
    }

    public function destroy(Request $request, WeeklyReport $report): Response
    {
        $this->reportService->delete($request->user(), $report);

        return response()->noContent();
    }
}
