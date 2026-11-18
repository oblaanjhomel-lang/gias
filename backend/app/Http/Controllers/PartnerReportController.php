<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartnerReportsRequest;
use App\Http\Requests\ReturnWeeklyReportRequest;
use App\Http\Resources\WeeklyReportResource;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Weekly reports from interns at the signed-in partner's site ({report} routes pass `can:review,report`).
 */
class PartnerReportController extends Controller
{
    public function __construct(private readonly WeeklyReportService $reportService) {}

    public function index(PartnerReportsRequest $request): AnonymousResourceCollection
    {
        $result = $this->reportService->forPartner($request->user(), $request->validated());

        return WeeklyReportResource::collection($result['reports'])->additional(['meta' => ['counts' => $result['counts']]]);
    }

    public function show(WeeklyReport $report): WeeklyReportResource
    {
        return new WeeklyReportResource($this->reportService->forPartnerShow($report));
    }

    public function sign(Request $request, WeeklyReport $report): WeeklyReportResource
    {
        return new WeeklyReportResource($this->reportService->sign($request->user(), $report));
    }

    public function return(ReturnWeeklyReportRequest $request, WeeklyReport $report): WeeklyReportResource
    {
        return new WeeklyReportResource(
            $this->reportService->return($request->user(), $report, $request->validated('note')),
        );
    }
}
