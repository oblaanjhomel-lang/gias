<?php

namespace App\Http\Controllers;

use App\Enums\EvaluationType;
use App\Http\Requests\SaveEvaluationRequest;
use App\Http\Resources\PartnerEvaluationFormResource;
use App\Http\Resources\PartnerEvaluationListResource;
use App\Models\Deployment;
use App\Services\EvaluationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in partner evaluates interns at their own site ({deployment}: EvaluationPolicy::partnerEvaluate).
 */
class PartnerEvaluationController extends Controller
{
    public function __construct(private readonly EvaluationService $evaluationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->evaluationService->partnerOverview($request->user());

        return PartnerEvaluationListResource::collection($result['rows'])->additional([
            'meta' => ['finals_submitted' => $result['finals_submitted'], 'total' => $result['total']],
        ]);
    }

    public function show(Deployment $deployment, string $type): PartnerEvaluationFormResource
    {
        return new PartnerEvaluationFormResource($this->evaluationService->forPartner($deployment, EvaluationType::from($type)));
    }

    /** Upsert the draft (partial ratings are fine). */
    public function update(SaveEvaluationRequest $request, Deployment $deployment, string $type): PartnerEvaluationFormResource
    {
        return new PartnerEvaluationFormResource($this->evaluationService->saveDraft(
            $request->user(),
            $deployment,
            EvaluationType::from($type),
            $request->validated('ratings') ?? [],
            $request->validated('comment'),
        ));
    }

    public function submit(Request $request, Deployment $deployment, string $type): PartnerEvaluationFormResource
    {
        return new PartnerEvaluationFormResource(
            $this->evaluationService->submit($request->user(), $deployment, EvaluationType::from($type)),
        );
    }
}
