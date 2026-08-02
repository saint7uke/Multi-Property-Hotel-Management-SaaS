<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\AuditLog;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\PaymentWorkflow;
use App\Services\ReservationWorkflow;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_payment_queue_is_property_scoped_and_generic_crud_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $ownPayment = Payment::query()->whereHas('reservation', fn ($query) => $query->where('property_id', $manager->property_id))->firstOrFail();
        $otherProperty = Property::whereKeyNot($manager->property_id)->firstOrFail();
        $otherPayment = $this->paymentForProperty($otherProperty);

        Filament::setCurrentPanel(Filament::getPanel('manager'));
        $this->actingAs($manager)
            ->get('/manager/payments')
            ->assertOk()
            ->assertSee($ownPayment->reservation->reference_number)
            ->assertDontSee($otherPayment->reservation->reference_number)
            ->assertSee('Payment reconciliation')
            ->assertSee('Pending verification');

        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($ownPayment));
        $this->assertFalse(PaymentResource::canDelete($ownPayment));

        $this->get('/manager/payments/create')->assertNotFound();
        $this->get("/manager/payments/{$ownPayment->id}/edit")->assertNotFound();
    }

    public function test_staff_reservations_generate_pending_payments_and_require_full_payment_before_check_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $room = Room::where('property_id', $manager->property_id)
            ->whereIn('status', ['available', 'ready'])
            ->whereDoesntHave('reservations')
            ->firstOrFail();

        $reservation = app(ReservationWorkflow::class)->createStaffReservation([
            'booking_type' => 'personal',
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'guest_name' => 'Finance Workflow Guest',
            'email' => 'finance-workflow@example.test',
            'phone' => '+63 900 111 2222',
            'check_in' => today()->addDays(10)->toDateString(),
            'check_out' => today()->addDays(12)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'status' => 'confirmed',
        ], $manager);

        $payment = $reservation->payments()->sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('pay_at_hotel', $payment->method);
        $this->assertSame((float) $reservation->estimated_total, (float) $payment->amount);

        try {
            app(ReservationWorkflow::class)->updateStatus($reservation->fresh(), 'checked_in', $manager);
            $this->fail('Check-in should require verified full payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        app(PaymentWorkflow::class)->markPaid($payment, [
            'method' => 'cash',
            'provider' => 'Front desk cash drawer',
            'provider_reference' => 'FINANCE-CHECKIN-001',
        ], $manager);

        app(ReservationWorkflow::class)->updateStatus($reservation->fresh(), 'checked_in', $manager);

        $this->assertSame('checked_in', $reservation->fresh()->status);
        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }

    public function test_failed_payment_retry_and_refund_preserve_transaction_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $payment = Payment::query()
            ->where('status', 'pending')
            ->whereHas('reservation', fn ($query) => $query->where('property_id', $manager->property_id))
            ->firstOrFail();
        $workflow = app(PaymentWorkflow::class);

        try {
            $workflow->markFailed($payment, '', $manager);
            $this->fail('A failed payment should require an internal reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('internal_notes', $exception->errors());
        }

        $workflow->markFailed($payment, 'Provider declined the transaction.', $manager);
        $this->assertSame('failed', $payment->reservation->fresh()->payment_status);

        $retry = $workflow->retry($payment->fresh(), $manager);
        $this->assertNotSame($payment->id, $retry->id);
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending', $retry->status);

        $workflow->markPaid($retry, [
            'method' => 'gcash',
            'provider' => 'GCash',
            'provider_reference' => 'FINANCE-RETRY-001',
            'internal_notes' => 'Verified against the merchant portal.',
        ], $manager);
        $this->assertSame('paid', $retry->reservation->fresh()->payment_status);

        $refund = $workflow->refund($retry->fresh(), 'Guest cancellation approved under policy.', $manager);

        $this->assertSame('paid', $retry->fresh()->status);
        $this->assertSame('refunded', $refund->status);
        $this->assertSame($retry->id, $refund->parent_payment_id);
        $this->assertNotNull($refund->refunded_at);
        $this->assertSame('refunded', $retry->reservation->fresh()->payment_status);
        $this->assertSame(4, AuditLog::where('subject_type', Payment::class)
            ->whereIn('subject_id', [$payment->id, $retry->id, $refund->id])
            ->whereIn('action', ['payments.failed', 'payments.retry_created', 'payments.verified', 'payments.refunded'])
            ->count());

        $this->actingAs($manager)
            ->getJson('/api/reports/summary?'.http_build_query([
                'from' => today()->toDateString(),
                'to' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('summary.revenue', 0);
    }

    public function test_event_quote_sets_the_collectible_total_before_payment_verification(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $reservation = app(ReservationWorkflow::class)->createStaffReservation([
            'booking_type' => 'event',
            'property_id' => $manager->property_id,
            'guest_name' => 'Event Finance Guest',
            'email' => 'event-finance@example.test',
            'phone' => '+63 900 333 4444',
            'event_name' => 'Leadership Retreat',
            'check_in' => today()->addDays(20)->toDateString(),
            'check_out' => today()->addDays(21)->toDateString(),
            'adults' => 12,
            'children' => 0,
            'status' => 'pending',
        ], $manager);
        $payment = $reservation->payments()->sole();
        $workflow = app(PaymentWorkflow::class);

        try {
            $workflow->markPaid($payment, [
                'method' => 'bank_transfer',
                'provider' => 'BDO',
                'provider_reference' => 'EVENT-NO-QUOTE-001',
            ], $manager);
            $this->fail('A zero-value event request cannot be marked paid.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $workflow->setEventQuote($payment, 85000, $manager, 'Venue, catering, and AV package.');

        $this->assertSame(85000.0, (float) $payment->fresh()->amount);
        $this->assertSame(85000.0, (float) $reservation->fresh()->estimated_total);

        $workflow->markPaid($payment->fresh(), [
            'method' => 'bank_transfer',
            'provider' => 'BDO',
            'provider_reference' => 'EVENT-PAID-001',
        ], $manager);

        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }

    private function paymentForProperty(Property $property): Payment
    {
        $guest = Guest::create([
            'name' => 'Finance Scope Guest',
            'email' => uniqid('finance-', true).'@example.test',
        ]);
        $reservation = Reservation::create([
            'reference_number' => 'FIN-SCOPE-'.strtoupper(uniqid()),
            'guest_id' => $guest->id,
            'property_id' => $property->id,
            'booking_type' => 'event',
            'event_name' => 'Finance Scope Event',
            'check_in' => today()->addMonth()->toDateString(),
            'check_out' => today()->addMonth()->addDay()->toDateString(),
            'adults' => 10,
            'children' => 0,
            'status' => 'pending',
            'payment_status' => 'pending',
            'estimated_total' => 50000,
            'source' => 'walk_in',
        ]);

        return Payment::create([
            'reservation_id' => $reservation->id,
            'method' => 'bank_transfer',
            'amount' => 50000,
            'status' => 'pending',
            'provider' => 'manual-review',
        ]);
    }
}
