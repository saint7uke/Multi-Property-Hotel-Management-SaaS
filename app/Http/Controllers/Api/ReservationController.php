<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffReservationRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationWorkflow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    use RecordsAudit;
    use ScopesPropertyAccess;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reservations.view'), 403);

        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
            'source' => ['nullable', 'in:public,walk_in'],
            'booking_type' => ['nullable', 'in:personal,event'],
            'property_id' => ['nullable', 'exists:properties,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'in:created_at,check_in,status,estimated_total'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = Reservation::query()->with('guest', 'property', 'room.property', 'payments');
        $this->scopeReservationsFor($request->user(), $query);

        $query
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($validated['booking_type'] ?? null, fn ($query, $bookingType) => $query->where('booking_type', $bookingType))
            ->when($validated['property_id'] ?? null, function ($query, $propertyId) {
                $query->where(function ($query) use ($propertyId) {
                    $query->where('property_id', $propertyId)
                        ->orWhereHas('room', fn ($room) => $room->where('property_id', $propertyId));
                });
            })
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('check_in', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('check_out', '<=', $to))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('event_name', 'like', "%{$search}%")
                        ->orWhereHas('guest', fn ($guest) => $guest->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            });

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        return response()->json($query->orderBy($sort, $direction)->paginate($validated['per_page'] ?? 10));
    }

    public function store(StaffReservationRequest $request, ReservationWorkflow $workflow): JsonResponse
    {
        $reservation = $workflow->createStaffReservation($request->validated(), $request->user())
            ->load('guest', 'property', 'room.property');

        return response()->json(['reservation' => $reservation], 201);
    }

    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        abort_unless($request->user()->can('reservations.view'), 403);
        abort_unless($this->canAccessReservation($request->user(), $reservation), 403);

        return response()->json(['reservation' => $reservation->load('guest', 'property', 'room.property', 'payments')]);
    }

    public function updateStatus(Request $request, Reservation $reservation, ReservationWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->can('reservations.manage'), 403);
        abort_unless($this->canAccessReservation($request->user(), $reservation), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
        ]);

        $reservation = $workflow->updateStatus($reservation, $validated['status'], $request->user());

        return response()->json(['reservation' => $reservation->load('guest', 'property', 'room.property')]);
    }

    private function estimateTotal(Room $room, string $checkIn, string $checkOut): float
    {
        return (float) $room->rate * max(1, Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)));
    }

    private function referenceNumber(): string
    {
        do {
            $reference = 'MAH-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Reservation::where('reference_number', $reference)->exists());

        return $reference;
    }
}
