<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentWorkflow
{
    public const METHOD_OPTIONS = [
        'credit_card' => 'Credit card',
        'debit_card' => 'Debit card',
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'online_banking' => 'Online banking',
        'bank_transfer' => 'Bank transfer',
        'digital_wallet' => 'Digital wallet',
        'cash' => 'Cash',
        'pay_at_hotel' => 'Pay at hotel',
    ];

    public function setEventQuote(Payment $payment, float $amount, User $actor, ?string $notes = null): Payment
    {
        $this->assertCanManage($actor, $payment);
        $this->assertStatus($payment, ['pending']);

        if ($payment->reservation?->booking_type !== 'event') {
            throw ValidationException::withMessages([
                'amount' => ['Only event or group requests can be quoted from the payment queue.'],
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Enter a quoted total greater than zero.']]);
        }

        return DB::transaction(function () use ($payment, $amount, $actor, $notes): Payment {
            $before = [
                'amount' => (float) $payment->amount,
                'estimated_total' => (float) $payment->reservation->estimated_total,
            ];

            $payment->update([
                'amount' => $amount,
                'internal_notes' => filled($notes) ? trim($notes) : $payment->internal_notes,
                'processed_by' => $actor->id,
            ]);
            $payment->reservation->update(['estimated_total' => $amount]);

            $this->audit($actor, 'payments.quote_updated', $payment, [
                'before' => $before,
                'after' => ['amount' => $amount, 'estimated_total' => $amount],
            ]);

            return $this->fresh($payment);
        });
    }

    public function markPaid(Payment $payment, array $data, User $actor): Payment
    {
        $this->assertCanManage($actor, $payment);
        $this->assertStatus($payment, ['pending']);

        if ($payment->reservation?->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => ['A cancelled reservation cannot receive a payment.']]);
        }

        if (! array_key_exists($data['method'] ?? '', self::METHOD_OPTIONS)) {
            throw ValidationException::withMessages(['method' => ['Choose a supported payment method.']]);
        }

        if ((float) $payment->amount <= 0 || $this->cents($payment->amount) !== $this->cents($payment->reservation?->estimated_total)) {
            throw ValidationException::withMessages([
                'amount' => ['The payment must match the reservation total before it can be verified.'],
            ]);
        }

        $provider = trim((string) ($data['provider'] ?? ''));
        $reference = trim((string) ($data['provider_reference'] ?? ''));

        if ($provider === '') {
            throw ValidationException::withMessages(['provider' => ['Enter the payment provider or receiving channel.']]);
        }

        if ($reference === '') {
            throw ValidationException::withMessages(['provider_reference' => ['Enter the provider transaction reference.']]);
        }

        if (Payment::where('provider_reference', $reference)->whereKeyNot($payment->id)->exists()) {
            throw ValidationException::withMessages(['provider_reference' => ['That provider reference is already recorded.']]);
        }

        if ($payment->reservation->payments()->where('status', 'paid')->whereKeyNot($payment->id)->exists()) {
            throw ValidationException::withMessages(['status' => ['This reservation already has a verified payment.']]);
        }

        return DB::transaction(function () use ($payment, $data, $actor, $provider, $reference): Payment {
            $payment->update([
                'method' => $data['method'],
                'status' => 'paid',
                'provider' => $provider,
                'provider_reference' => $reference,
                'paid_at' => now(),
                'refunded_at' => null,
                'processed_by' => $actor->id,
                'internal_notes' => filled($data['internal_notes'] ?? null) ? trim($data['internal_notes']) : null,
            ]);

            $this->syncReservationStatus($payment);
            $this->audit($actor, 'payments.verified', $payment, [
                'status' => 'paid',
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'provider' => $provider,
                'provider_reference' => $reference,
            ]);

            return $this->fresh($payment);
        });
    }

    public function markFailed(Payment $payment, string $reason, User $actor): Payment
    {
        $this->assertCanManage($actor, $payment);
        $this->assertStatus($payment, ['pending']);

        if (blank($reason)) {
            throw ValidationException::withMessages(['internal_notes' => ['Add a reason for the failed payment.']]);
        }

        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $payment->update([
                'status' => 'failed',
                'processed_by' => $actor->id,
                'internal_notes' => trim($reason),
            ]);

            $this->syncReservationStatus($payment);
            $this->audit($actor, 'payments.failed', $payment, ['reason' => trim($reason)]);

            return $this->fresh($payment);
        });
    }

    public function retry(Payment $payment, User $actor): Payment
    {
        $this->assertCanManage($actor, $payment);
        $this->assertStatus($payment, ['failed', 'cancelled']);

        if ($payment->reservation?->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => ['A cancelled reservation cannot start another payment attempt.']]);
        }

        if ((float) $payment->reservation?->estimated_total <= 0) {
            throw ValidationException::withMessages(['amount' => ['Set the reservation total before retrying payment.']]);
        }

        if ($payment->reservation->payments()->whereIn('status', ['pending', 'paid'])->exists()) {
            throw ValidationException::withMessages(['status' => ['This reservation already has an active payment attempt.']]);
        }

        return DB::transaction(function () use ($payment, $actor): Payment {
            $retry = Payment::create([
                'reservation_id' => $payment->reservation_id,
                'method' => $payment->method,
                'amount' => $payment->reservation->estimated_total,
                'status' => 'pending',
                'provider' => 'manual-review',
            ]);

            $this->syncReservationStatus($retry);
            $this->audit($actor, 'payments.retry_created', $retry, [
                'previous_payment_id' => $payment->id,
                'amount' => (float) $retry->amount,
            ]);

            return $this->fresh($retry);
        });
    }

    public function refund(Payment $payment, string $reason, User $actor): Payment
    {
        $this->assertCanManage($actor, $payment);
        $this->assertStatus($payment, ['paid']);

        if (blank($reason)) {
            throw ValidationException::withMessages(['internal_notes' => ['Add a reason for the refund.']]);
        }

        if ($payment->refunds()->where('status', 'refunded')->exists()) {
            throw ValidationException::withMessages(['status' => ['This payment has already been refunded.']]);
        }

        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $refund = Payment::create([
                'reservation_id' => $payment->reservation_id,
                'parent_payment_id' => $payment->id,
                'method' => $payment->method,
                'amount' => $payment->amount,
                'status' => 'refunded',
                'provider' => $payment->provider,
                'provider_reference' => $payment->provider_reference.'-REFUND-'.now()->format('YmdHis'),
                'refunded_at' => now(),
                'processed_by' => $actor->id,
                'internal_notes' => trim($reason),
            ]);

            $this->syncReservationStatus($refund);
            $this->audit($actor, 'payments.refunded', $refund, [
                'original_payment_id' => $payment->id,
                'amount' => (float) $payment->amount,
                'reason' => trim($reason),
            ]);

            return $this->fresh($refund);
        });
    }

    private function syncReservationStatus(Payment $payment): void
    {
        $payments = $payment->reservation->payments()->get(['status', 'amount']);
        $statuses = $payments->pluck('status');
        $paidTotal = (float) $payments->where('status', 'paid')->sum('amount');
        $refundedTotal = (float) $payments->where('status', 'refunded')->sum('amount');
        $status = match (true) {
            $paidTotal > 0 && $refundedTotal >= $paidTotal => 'refunded',
            $paidTotal > $refundedTotal => 'paid',
            $statuses->contains('pending') => 'pending',
            $refundedTotal > 0 => 'refunded',
            $statuses->contains('failed') => 'failed',
            $statuses->contains('cancelled') => 'cancelled',
            default => 'pending',
        };

        $payment->reservation->update(['payment_status' => $status]);
    }

    private function assertCanManage(User $actor, Payment $payment): void
    {
        $propertyId = $payment->reservation?->property_id ?? $payment->reservation?->room?->property_id;

        if (! $actor->can('payments.manage')
            || (! $actor->hasRole('admin') && (! $actor->property_id || (int) $propertyId !== (int) $actor->property_id))) {
            abort(403);
        }
    }

    private function assertStatus(Payment $payment, array $allowed): void
    {
        if (! in_array($payment->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['That payment action is not available for the current status.'],
            ]);
        }
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fresh(Payment $payment): Payment
    {
        return $payment->fresh()->load('reservation.guest', 'reservation.property', 'reservation.room.property', 'processedBy');
    }

    private function audit(User $actor, string $action, Payment $payment, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $payment::class,
            'subject_id' => $payment->id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
