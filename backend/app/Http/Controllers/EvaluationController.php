<?php

namespace App\Http\Controllers;

use App\Http\Requests\CoordinatorRatingRequest;
use App\Http\Requests\EvaluationListRequest;
use App\Http\Requests\EvaluationSummaryRequest;
use App\Http\Requests\ReturnEvaluationRequest;
use App\Http\Resources\EvaluationQueueRowResource;
use App\Http\Resources\EvaluationResource;
use App\Http\Resources\EvaluationSummaryResource;
use App\Models\Evaluation;
use App\Services\EvaluationReportService;
use App\Services\EvaluationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Coordinator (own program) / admin review of evaluations ({evaluation}: EvaluationPolicy::review).
 */
class EvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluationService,
        private readonly EvaluationReportService $reportService,
    ) {}

    public function summary(EvaluationSummaryRequest $request): EvaluationSummaryResource
    {
        return new EvaluationSummaryResource($this->reportService->summary($request->user(), $request->evaluationType()));
    }

    public function index(EvaluationListRequest $request): AnonymousResourceCollection
    {
        $result = $this->evaluationService->list($request->user(), $request->validated());

        return EvaluationQueueRowResource::collection($result['deployments'])->additional([
            'meta' => ['type' => $result['type']->value, 'counts' => $result['counts']],
        ]);
    }

    public function show(Evaluation $evaluation): EvaluationResource
    {
        return new EvaluationResource($this->evaluationService->detail($evaluation));
    }

    public function coordinatorRating(CoordinatorRatingRequest $request, Evaluation $evaluation): EvaluationResource
    {
        return new EvaluationResource($this->evaluationService->setCoordinatorRating(
            $request->user(), $evaluation, (int) $request->validated('rating'), $request->validated('note'),
        ));
    }

    public function review(Request $request, Evaluation $evaluation): EvaluationResource
    {
        return new EvaluationResource($this->evaluationService->review($request->user(), $evaluation));
    }

    public function reopen(Request $request, Evaluation $evaluation): EvaluationResource
    {
        return new EvaluationResource($this->evaluationService->reopen($request->user(), $evaluation));
    }

    public function return(ReturnEvaluationRequest $request, Evaluation $evaluation): EvaluationResource
    {
        return new EvaluationResource(
            $this->evaluationService->returnToPartner($request->user(), $evaluation, $request->validated('note')),
        );
    }
}
