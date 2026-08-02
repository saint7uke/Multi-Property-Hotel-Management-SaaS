<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Reservation;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesPropertyAccess
{
    protected function scopeRoomsFor(User $user, Builder $query): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        if (! $user->property_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('property_id', $user->property_id);
    }

    protected function scopeReservationsFor(User $user, Builder $query): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        if (! $user->property_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->where('property_id', $user->property_id)
                ->orWhereHas('room', fn (Builder $room) => $room->where('property_id', $user->property_id));
        });
    }

    protected function scopeReviewsFor(User $user, Builder $query): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        if (! $user->property_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('property_id', $user->property_id);
    }

    protected function canAccessRoom(User $user, Room $room): bool
    {
        return $user->hasRole('admin') || ($user->property_id && (int) $room->property_id === (int) $user->property_id);
    }

    protected function canAccessProperty(User $user, int|string|null $propertyId): bool
    {
        if (! $propertyId) {
            return false;
        }

        return $user->hasRole('admin') || ($user->property_id && (int) $propertyId === (int) $user->property_id);
    }

    protected function canAccessReservation(User $user, Reservation $reservation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $user->property_id) {
            return false;
        }

        $reservation->loadMissing('room');

        return (int) $reservation->property_id === (int) $user->property_id
            || (int) $reservation->room?->property_id === (int) $user->property_id;
    }

    protected function canAccessReview(User $user, Review $review): bool
    {
        return $user->hasRole('admin') || ($user->property_id && (int) $review->property_id === (int) $user->property_id);
    }
}
