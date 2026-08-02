<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewWorkflow
{
    private const STATUS_TRANSITIONS = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['pending'],
        'rejected' => ['pending'],
    ];

    public function submit(array $data): Review
    {
        return DB::transaction(function () use ($data): Review {
            $reservation = $this->verifiedReservation($data);

            $guest = $reservation?->guest ?? Guest::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['guest_name']],
            );

            $propertyId = $reservation
                ? ($reservation->property_id ?? $reservation->room?->property_id)
                : $data['property_id'];

            $review = Review::create([
                'guest_id' => $guest->id,
                'reservation_id' => $reservation?->id,
                'property_id' => $propertyId,
                'rating' => $data['rating'],
                'stay_type' => $data['stay_type'] ?? null,
                'message' => $data['message'],
                'status' => 'pending',
            ]);

            $this->audit(null, 'reviews.public_submitted', $review, [
                'property_id' => $review->property_id,
                'reservation_id' => $review->reservation_id,
                'rating' => $review->rating,
                'verified' => (bool) $review->reservation_id,
            ]);

            return $review->load('guest', 'property', 'reservation');
        });
    }

    public function moderate(Review $review, string $status, User $actor, ?string $notes = null): Review
    {
        if (! $actor->can('reviews.moderate') || ! $this->canAccess($actor, $review)) {
            abort(403);
        }

        if (! in_array($status, self::STATUS_TRANSITIONS[$review->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ['That moderation transition is not available.'],
            ]);
        }

        $notes = filled($notes) ? trim($notes) : null;

        if ($status === 'rejected' && blank($notes)) {
            throw ValidationException::withMessages([
                'moderation_notes' => ['Add an internal reason before rejecting this review.'],
            ]);
        }

        $previousStatus = $review->status;
        $review->update([
            'status' => $status,
            'moderation_notes' => $status === 'pending' ? null : $notes,
            'moderated_by' => $actor->id,
            'moderated_at' => now(),
        ]);

        $this->audit($actor, 'reviews.moderated', $review, [
            'from' => $previousStatus,
            'to' => $status,
            'moderation_notes' => $status === 'rejected' ? $notes : null,
        ]);

        return $review->fresh()->load('guest', 'property', 'reservation', 'moderatedBy');
    }

    private function verifiedReservation(array $data): ?Reservation
    {
        if (empty($data['reference_number'])) {
            return null;
        }

        $reservation = Reservation::query()
            ->with('guest', 'room')
            ->where('reference_number', $data['reference_number'])
            ->whereHas('guest', fn ($query) => $query->whereRaw('lower(email) = ?', [$data['email']]))
            ->first();

        if (! $reservation) {
            throw ValidationException::withMessages([
                'reference_number' => ['We could not verify that booking reference with this email.'],
            ]);
        }

        if ($reservation->status !== 'checked_out') {
            throw ValidationException::withMessages([
                'reference_number' => ['Verified stay reviews can be submitted after checkout.'],
            ]);
        }

        if (Review::where('reservation_id', $reservation->id)->whereIn('status', ['pending', 'approved'])->exists()) {
            throw ValidationException::withMessages([
                'reference_number' => ['A review for this booking is already waiting for moderation or published.'],
            ]);
        }

        return $reservation;
    }

    private function canAccess(User $user, Review $review): bool
    {
        return $user->hasRole('admin')
            || ($user->property_id && (int) $review->property_id === (int) $user->property_id);
    }

    private function audit(?User $actor, string $action, Review $review, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $review::class,
            'subject_id' => $review->id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
