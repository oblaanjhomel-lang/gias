<?php

namespace App\Services;

use App\Models\RecommendationFeedback;
use App\Models\User;

/**
 * A coordinator's / admin's 1–5 rating of one algorithm's list for a student, stored with a
 * snapshot of that list for later evaluation.
 */
class FeedbackService
{
    public function __construct(private readonly RecommendationService $recommendations) {}

    public function store(User $actor, User $studentUser, string $algorithm, int $rating, ?string $comment): RecommendationFeedback
    {
        $result = $this->recommendations->recommendFor($studentUser, $actor);

        return RecommendationFeedback::create([
            'student_id' => $studentUser->student()->firstOrFail()->id,
            'algorithm' => $algorithm,
            'rating' => $rating,
            'comment' => $comment,
            'rated_by' => $actor->id,
            'snapshot' => $this->recommendations->snapshot($result, $algorithm),
        ]);
    }
}
