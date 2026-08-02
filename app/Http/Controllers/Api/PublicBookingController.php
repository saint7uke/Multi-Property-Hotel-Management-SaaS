<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicBookingRequest;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicBookingController extends Controller
{
    use RecordsAudit;

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'exists:properties,id'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:40'],
            'preferred_area' => ['nullable', 'string', 'max:160'],
        ]);

        $rooms = Room::query()
            ->with('property')
            ->whereIn('status', ['available', 'ready'])
            ->when($validated['property_id'] ?? null, fn ($query, $propertyId) => $query->where('property_id', $propertyId))
            ->when($validated['preferred_area'] ?? null, function ($query, $area) {
                $query->whereHas('property', function ($propertyQuery) use ($area) {
                    $propertyQuery->where('name', 'like', "%{$area}%")
                        ->orWhere('address', 'like', "%{$area}%")
                        ->orWhere('city', 'like', "%{$area}%")
                        ->orWhere('country', 'like', "%{$area}%");
                });
            })
            ->when($validated['guests'] ?? null, fn ($query, $guests) => $query->where('capacity', '>=', $guests))
            ->when(
                isset($validated['check_in'], $validated['check_out']),
                fn ($query) => $query->whereDoesntHave(
                    'reservations',
                    fn ($reservation) => $reservation->overlapping($validated['check_in'], $validated['check_out'])
                )
            )
            ->orderBy('property_id')
            ->orderBy('room_number')
            ->get();

        return response()->json(['data' => $rooms]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'room_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:40'],
            'wants_breakfast' => ['nullable', 'boolean'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['string', 'in:extra_bed,extra_pax,additional_breakfast,early_check_in,late_check_out'],
        ]);

        $room = Room::findOrFail($validated['room_id']);

        return response()->json([
            'nights' => $this->nights($validated['check_in'], $validated['check_out']),
            'estimated_total' => $this->estimateTotal(
                $room,
                $validated['check_in'],
                $validated['check_out'],
                $validated['room_count'] ?? 1,
                $validated['guests'] ?? 1,
                (bool) ($validated['wants_breakfast'] ?? false),
                $validated['addons'] ?? [],
            ),
        ]);
    }

    public function store(PublicBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $room = isset($validated['room_id']) ? Room::find($validated['room_id']) : null;

        if ($room && (int) $room->property_id !== (int) $validated['property_id']) {
            return response()->json([
                'message' => 'The selected room does not belong to the selected property.',
                'errors' => ['room_id' => ['Choose a room from the selected property.']],
            ], 422);
        }

        $guest = Guest::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['guest_name'],
                'phone' => $validated['phone'],
                'address' => $validated['home_address'],
            ]
        );

        $property = Property::findOrFail($validated['property_id']);
        $wantsBreakfast = (bool) ($validated['wants_breakfast'] ?? false);
        if (! $property->offers_breakfast) {
            $wantsBreakfast = false;
        }

        $addons = $validated['addons'] ?? [];
        $estimatedTotal = $room
            ? $this->estimateTotal($room, $validated['check_in'], $validated['check_out'], $validated['room_count'] ?? 1, ($validated['adults'] ?? 1) + ($validated['children'] ?? 0), $wantsBreakfast, $addons)
            : 0;

        $reservation = Reservation::create([
            'reference_number' => $this->referenceNumber(),
            'guest_id' => $guest->id,
            'property_id' => $validated['property_id'],
            'room_id' => $room?->id,
            'booking_type' => $validated['booking_type'],
            'event_name' => $validated['event_name'] ?? null,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'adults' => $validated['adults'],
            'children' => $validated['children'] ?? 0,
            'room_count' => $validated['room_count'] ?? 1,
            'preferred_area' => $validated['preferred_area'] ?? null,
            'wants_breakfast' => $wantsBreakfast,
            'addons' => $addons ?: null,
            'payment_method' => $validated['payment_method'],
            'terms_accepted_at' => now(),
            'special_request' => $validated['special_request'] ?? null,
            'status' => 'pending',
            'payment_status' => 'pending',
            'estimated_total' => $estimatedTotal,
            'source' => 'public',
        ])->load('guest', 'property', 'room.property');

        Payment::create([
            'reservation_id' => $reservation->id,
            'method' => $validated['payment_method'],
            'amount' => $estimatedTotal,
            'status' => 'pending',
            'provider' => 'manual-review',
            'provider_reference' => $reservation->reference_number.'-PENDING',
        ]);
        $reservation->load('payments');

        $this->audit($request, 'reservations.public_submitted', $reservation, [
            'reference_number' => $reservation->reference_number,
            'property_id' => $reservation->property_id,
            'room_id' => $reservation->room_id,
            'booking_type' => $reservation->booking_type,
            'source' => $reservation->source,
        ]);

        return response()->json([
            'message' => 'Booking request submitted for staff review.',
            'reservation' => $reservation,
        ], 201);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email'],
        ]);

        $referenceNumber = Str::of($validated['reference_number'])
            ->replaceMatches('/\s+/', '')
            ->upper()
            ->toString();
        $email = Str::of($validated['email'])->trim()->lower()->toString();

        $reservation = Reservation::query()
            ->with('guest', 'property', 'room.property', 'payments')
            ->where('reference_number', $referenceNumber)
            ->whereHas('guest', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))
            ->first();

        if (! $reservation) {
            return response()->json([
                'message' => 'No booking matched that reference number and email.',
            ], 404);
        }

        return response()->json([
            'reservation' => [
                'reference_number' => $reservation->reference_number,
                'booking_type' => $reservation->booking_type,
                'event_name' => $reservation->event_name,
                'property' => $reservation->property ? [
                    'name' => $reservation->property->name,
                    'city' => $reservation->property->city,
                    'country' => $reservation->property->country,
                ] : null,
                'room' => $reservation->room ? [
                    'room_number' => $reservation->room->room_number,
                    'type' => $reservation->room->type,
                    'property' => $reservation->room->property?->name,
                ] : null,
                'guest_name' => $reservation->guest->name,
                'check_in' => $reservation->check_in?->toDateString(),
                'check_out' => $reservation->check_out?->toDateString(),
                'adults' => $reservation->adults,
                'children' => $reservation->children,
                'room_count' => $reservation->room_count,
                'preferred_area' => $reservation->preferred_area,
                'wants_breakfast' => $reservation->wants_breakfast,
                'addons' => $reservation->addons ?? [],
                'payment_method' => $reservation->payment_method,
                'status' => $reservation->status,
                'payment_status' => $reservation->payment_status,
                'estimated_total' => $reservation->estimated_total,
                'next_step' => $this->nextStep($reservation),
                'submitted_at' => $reservation->created_at?->toISOString(),
                'payments' => $reservation->payments->map(fn ($payment) => [
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at?->toISOString(),
                ])->values(),
            ],
        ]);
    }

    private function nextStep(Reservation $reservation): string
    {
        return match ($reservation->status) {
            'pending' => $reservation->booking_type === 'event'
                ? 'Our team is reviewing the event or group request and will confirm pricing.'
                : 'Our team is reviewing room availability and payment details.',
            'confirmed' => 'Your booking is confirmed. Please keep this reference number for check-in.',
            'checked_in' => 'This booking is currently checked in.',
            'checked_out' => 'This booking has been completed. You may submit a guest review.',
            'cancelled' => 'This booking has been cancelled. Contact the hotel if you need help.',
            default => 'Please contact the hotel for the latest update.',
        };
    }

    private function estimateTotal(Room $room, string $checkIn, string $checkOut, int $roomCount = 1, int $guestCount = 1, bool $wantsBreakfast = false, array $addons = []): float
    {
        $nights = $this->nights($checkIn, $checkOut);
        $roomCount = max(1, $roomCount);
        $guestCount = max(1, $guestCount);
        $base = (float) $room->rate * $nights * $roomCount;
        $addonTotal = collect($addons)->sum(fn (string $addon) => match ($addon) {
            'extra_bed' => 1200 * $nights,
            'extra_pax' => 900 * $nights,
            'additional_breakfast' => 450 * $nights,
            'early_check_in' => 1500,
            'late_check_out' => 1500,
            default => 0,
        });
        $breakfastTotal = $wantsBreakfast ? 450 * $guestCount * $nights : 0;

        return $base + $addonTotal + $breakfastTotal;
    }

    private function nights(string $checkIn, string $checkOut): int
    {
        return max(1, Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)));
    }

    private function referenceNumber(): string
    {
        do {
            $reference = 'MAH-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Reservation::where('reference_number', $reference)->exists());

        return $reference;
    }
}
