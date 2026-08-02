<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicReviewRequest;
use App\Services\ReviewWorkflow;
use Illuminate\Http\JsonResponse;

class PublicReviewController extends Controller
{
    public function store(PublicReviewRequest $request, ReviewWorkflow $workflow): JsonResponse
    {
        $review = $workflow->submit($request->validated());

        return response()->json([
            'message' => $review->reservation_id
                ? 'Thank you. Your verified stay review is waiting for moderation.'
                : 'Thank you. Your review is waiting for moderation.',
            'review' => array_merge($review->toArray(), ['verified' => (bool) $review->reservation_id]),
        ], 201);
    }
}
