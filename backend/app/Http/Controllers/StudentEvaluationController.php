<?php

namespace App\Http\Controllers;

use App\Http\Resources\EvaluationResource;
use App\Services\EvaluationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in student's REVIEWED evaluations (no drafts, no notes).
 */
class StudentEvaluationController extends Controller
{
    public function __construct(private readonly EvaluationService $evaluationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return EvaluationResource::collection($this->evaluationService->forStudent($request->user()));
    }
}
