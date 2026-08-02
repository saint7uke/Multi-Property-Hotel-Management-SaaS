<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationWorkflow
{
    /** @var array<string, array<int, string>> */
    private const STATUS_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled'],
        'checked_in' => ['checked_out'],
        'checked_out' => [],
        'cancelled' => [],
    ];

    public function createStaffReservation(array $data, User $actor): Reservation
    {
        $this->validateStayDetails($data);

        $room = null;
        $propertyId = $data['property_id'] ?? null;
        $estimatedTotal = 0;

        if ($data['booking_type'] === 'personal') {
            $room = Room::findOrFail($data['room_id']);

            if (! $this->canAccessRoom($actor, $room)) {
                abort(403);
            }

            if (! in_array($room->status, ['available', 'ready'], true)) {
                throw ValidationException::withMessages(['room_id' => ['Choose a room that is available or ready.']]);
            }

            if ($room->reservations()->overlapping($data['check_in'], $data['check_out'])->exists()) {
                throw ValidationException::withMessages(['room_id' => ['This room already has an overlapping reservation for those dates.']]);
            }

            if (((int) $data['adults'] + (int) ($data['children'] ?? 0)) > $room->capacity) {
                throw ValidationException::withMessages([
                    'adults' => ["Room {$room->room_number} allows up to {$room->capacity} guests."],
                ]);
            }

            $propertyId = $room->property_id;
            $estimatedTotal = (float) $room->rate * max(1, Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out'])));
        }

        if ($data['booking_type'] === 'event' && ! $this->canAccessProperty($actor, $propertyId)) {
            abort(403);
        }

        $guest = Guest::updateOrCreate(
            ['email' => $data['email']],
            ['name' => $data['guest_name'], 'phone' => $data['phone']]
        );

        $reservation = Reservation::create([
            'reference_number' => $this->referenceNumber(),
            'guest_id' => $guest->id,
            'property_id' => $propertyId,
            'room_id' => $room?->id,
            'booking_type' => $data['booking_type'],
            'event_name' => $data['event_name'] ?? null,
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'adults' => $data['adults'],
            'children' => $data['children'] ?? 0,
            'special_request' => $data['special_request'] ?? null,
            'status' => $data['status'] ?? ($data['booking_type'] === 'event' ? 'pending' : 'confirmed'),
            'payment_status' => 'pending',
            'estimated_total' => $estimatedTotal,
            'source' => 'walk_in',
            'created_by' => $actor->id,
        ]);

        Payment::create([
            'reservation_id' => $reservation->id,
            'method' => 'pay_at_hotel',
            'amount' => $estimatedTotal,
            'status' => 'pending',
            'provider' => 'staff-entry',
            'provider_reference' => $reservation->reference_number.'-PENDING',
        ]);

        $this->audit($actor, 'reservations.created', $reservation, $reservation->toArray());

        return $reservation;
    }

    public function updateStatus(Reservation $reservation, string $status, User $actor): Reservation
    {
        if (! $this->canAccessReservation($actor, $reservation)) {
            abort(403);
        }

        if (! in_array($status, self::STATUS_TRANSITIONS[$reservation->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["A {$reservation->status} reservation cannot be changed to {$status}."],
            ]);
        }

        if ($status === 'checked_in' && $reservation->room && ! in_array($reservation->room->status, ['available', 'ready'], true)) {
            throw ValidationException::withMessages([
                'status' => ['The assigned room must be available or ready before check-in.'],
            ]);
        }

        if ($status === 'checked_in' && $reservation->payment_status !== 'paid') {
            throw ValidationException::withMessages([
                'status' => ['Full payment must be verified before check-in.'],
            ]);
        }

        $before = $reservation->only(['status', 'payment_status']);
        $reservation->update(['status' => $status]);

        if ($status === 'cancelled' && ! $reservation->payments()->where('status', 'paid')->exists()) {
            $reservation->payments()->where('status', 'pending')->update([
                'status' => 'cancelled',
                'processed_by' => $actor->id,
                'internal_notes' => 'Reservation cancelled.',
            ]);
            $reservation->update(['payment_status' => 'cancelled']);
        }

        if ($reservation->room) {
            if ($status === 'checked_in') {
                $reservation->room->update(['status' => 'occupied']);
            }

            if ($status === 'checked_out') {
                $reservation->room->update(['status' => 'dirty']);
                $reservation->room->housekeepingTask()->updateOrCreate(
                    ['room_id' => $reservation->room_id],
                    ['status' => 'dirty', 'shift_date' => now()->toDateString()]
                );
            }
        }

        $fresh = $reservation->fresh();
        $this->audit($actor, 'reservations.status_changed', $reservation, [
            'before' => $before,
            'after' => $fresh->only(['status', 'payment_status']),
        ]);

        return $fresh;
    }

    /** @return array<string, string> */
    public function availableStatuses(Reservation $reservation): array
    {
        $labels = [
            'confirmed' => 'Confirm reservation',
            'checked_in' => 'Check in guest',
            'checked_out' => 'Check out guest',
            'cancelled' => 'Cancel reservation',
        ];

        return collect(self::STATUS_TRANSITIONS[$reservation->status] ?? [])
            ->mapWithKeys(fn (string $status): array => [$status => $labels[$status]])
            ->all();
    }

    public function audit(User $actor, string $action, object $subject, array $changes = []): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => (string) request()?->userAgent(),
        ]);
    }

    private function canAccessRoom(User $user, Room $room): bool
    {
        return $user->hasRole('admin') || ($user->property_id && (int) $room->property_id === (int) $user->property_id);
    }

    private function canAccessProperty(User $user, int|string|null $propertyId): bool
    {
        return $user->hasRole('admin') || ($user->property_id && (int) $propertyId === (int) $user->property_id);
    }

    private function canAccessReservation(User $user, Reservation $reservation): bool
    {
        $propertyId = $reservation->property_id ?? $reservation->room?->property_id;

        return $this->canAccessProperty($user, $propertyId);
    }

    private function validateStayDetails(array $data): void
    {
        $checkIn = Carbon::parse($data['check_in'] ?? null);
        $checkOut = Carbon::parse($data['check_out'] ?? null);

        if (! $checkOut->greaterThan($checkIn)) {
            throw ValidationException::withMessages([
                'check_out' => ['Check-out must be after check-in.'],
            ]);
        }

        if (($data['booking_type'] ?? null) === 'event' && blank($data['event_name'] ?? null)) {
            throw ValidationException::withMessages([
                'event_name' => ['Enter an event or group name.'],
            ]);
        }
    }

    private function referenceNumber(): string
    {
        do {
            $reference = 'MAH-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Reservation::where('reference_number', $reference)->exists());

        return $reference;
    }
}
