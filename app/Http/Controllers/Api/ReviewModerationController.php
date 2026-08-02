<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewModerationController extends Controller
{
    use ScopesPropertyAccess;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reviews.moderate'), 403);

        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'verified' => ['nullable', 'in:verified,unverified'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Review::query()->with('guest', 'property', 'reservation', 'moderatedBy');
        $this->scopeReviewsFor($request->user(), $query);

        return response()->json($query
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($validated['verified'] ?? null) === 'verified', fn ($query) => $query->whereNotNull('reservation_id'))
            ->when(($validated['verified'] ?? null) === 'unverified', fn ($query) => $query->whereNull('reservation_id'))
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('message', 'like', "%{$search}%")
                        ->orWhereHas('guest', function ($guest) use ($search) {
                            $guest->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('property', fn ($property) => $property->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('reservation', fn ($reservation) => $reservation->where('reference_number', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("case when status = 'pending' then 0 when status = 'approved' then 1 else 2 end")
            ->latest()
            ->paginate($validated['per_page'] ?? 10));
    }

    public function update(Request $request, Review $review, ReviewWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->can('reviews.moderate'), 403);
        abort_unless($this->canAccessReview($request->user(), $review), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
            'moderation_notes' => ['nullable', 'string', 'max:1000', 'required_if:status,rejected'],
        ]);

        $review = $workflow->moderate(
            $review,
            $validated['status'],
            $request->user(),
            $validated['moderation_notes'] ?? null,
        );

        return response()->json(['review' => $review]);
    }
}
