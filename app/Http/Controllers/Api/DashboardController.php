<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ScopesPropertyAccess;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('dashboard.view'), 403);

        $reservations = $this->scopeReservationsFor($user, Reservation::query());
        $rooms = $this->scopeRoomsFor($user, Room::query());

        $revenue = Payment::query()
            ->where('status', 'paid')
            ->whereHas('reservation', fn ($query) => $this->scopeReservationsFor($user, $query))
            ->sum('amount');

        return response()->json([
            'stats' => [
                'pending_reservations' => (clone $reservations)->where('status', 'pending')->count(),
                'pending_public_reservations' => (clone $reservations)->where('status', 'pending')->where('source', 'public')->count(),
                'confirmed_reservations' => (clone $reservations)->where('status', 'confirmed')->count(),
                'occupancy_rate' => $this->occupancyRate((clone $rooms)),
                'revenue' => (float) $revenue,
                'dirty_rooms' => (clone $rooms)->whereIn('status', ['dirty', 'cleaning'])->count(),
            ],
        ]);
    }

    private function occupancyRate($rooms): float
    {
        $total = (clone $rooms)->count();

        if ($total === 0) {
            return 0;
        }

        return round(((clone $rooms)->where('status', 'occupied')->count() / $total) * 100, 1);
    }
}
