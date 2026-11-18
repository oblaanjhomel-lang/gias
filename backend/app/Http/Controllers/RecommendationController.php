<?php

namespace App\Http\Controllers;

use App\Http\Requests\DraftRecommendationsRequest;
use App\Http\Requests\StoreRecommendationFeedbackRequest;
use App\Http\Resources\MatchingStudentResource;
use App\Http\Resources\PartnerSuggestionResource;
use App\Http\Resources\PlacementStudentResource;
use App\Http\Resources\RecommendationFeedbackResource;
use App\Http\Resources\RecommendationResource;
use App\Models\User;
use App\Services\FeedbackService;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Advisory recommendations. {student} is a student USER id; the route's `can:manageStudent`
 * keeps coordinators to their own program.
 */
class RecommendationController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendationService,
        private readonly FeedbackService $feedbackService,
    ) {}

    public function students(Request $request): AnonymousResourceCollection
    {
        $result = $this->recommendationService->studentsForPlacement($request->user());

        return PlacementStudentResource::collection($result['students'])->additional([
            'meta' => ['placed' => $result['placed'], 'total' => $result['total']],
        ]);
    }

    public function show(Request $request, User $student): RecommendationResource
    {
        return new RecommendationResource($this->recommendationService->recommendFor($student, $request->user()));
    }

    public function draft(DraftRecommendationsRequest $request): RecommendationResource
    {
        return new RecommendationResource(
            $this->recommendationService->draft($request->user(), $request->validated('algorithm')),
        );
    }

    public function feedback(StoreRecommendationFeedbackRequest $request, User $student): RecommendationFeedbackResource
    {
        return new RecommendationFeedbackResource($this->feedbackService->store(
            $request->user(),
            $student,
            $request->validated('algorithm'),
            (int) $request->validated('rating'),
            $request->validated('comment'),
        ));
    }

    /** Student: top 3 partners (WSM), no breakdowns. */
    public function forStudent(Request $request): AnonymousResourceCollection
    {
        return PartnerSuggestionResource::collection(
            $this->recommendationService->suggestionsForStudent($request->user()),
        );
    }

    /** Partner: top 3 unplaced students (WSM), name and program only. */
    public function forPartner(Request $request): AnonymousResourceCollection
    {
        $result = $this->recommendationService->matchingStudentsForPartner($request->user());

        return MatchingStudentResource::collection($result['students'])->additional([
            'meta' => ['open' => $result['open'], 'intern_slots' => $result['intern_slots']],
        ]);
    }
}
