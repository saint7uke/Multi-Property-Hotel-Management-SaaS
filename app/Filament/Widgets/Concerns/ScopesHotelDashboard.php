<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesHotelDashboard
{
    protected function dashboardUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected function reservationsQuery(): Builder
    {
        $query = Reservation::query();
        $user = $this->dashboardUser();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('property_id', $user?->property_id ?? 0)
                ->orWhereHas('room', fn (Builder $room) => $room->where('property_id', $user?->property_id ?? 0));
        });
    }

    protected function roomsQuery(): Builder
    {
        $query = Room::query();
        $user = $this->dashboardUser();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        return $query->where('property_id', $user?->property_id ?? 0);
    }

    protected function paymentsQuery(): Builder
    {
        $user = $this->dashboardUser();

        return Payment::query()->whereHas('reservation', function (Builder $query) use ($user): void {
            if ($user?->hasRole('admin')) {
                return;
            }

            $query->where(function (Builder $reservations) use ($user): void {
                $reservations->where('property_id', $user?->property_id ?? 0)
                    ->orWhereHas('room', fn (Builder $room) => $room->where('property_id', $user?->property_id ?? 0));
            });
        });
    }
}
