<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ContactInquiry;
use App\Models\NewsletterSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestCommunicationWorkflow
{
    public function submitInquiry(array $data): ContactInquiry
    {
        return DB::transaction(function () use ($data): ContactInquiry {
            $inquiry = ContactInquiry::create([
                ...collect($data)->except('website')->all(),
                'reference_number' => $this->inquiryReference(),
                'status' => 'new',
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);

            $this->audit(null, 'inquiries.public_submitted', $inquiry, [
                'inquiry_type' => $inquiry->inquiry_type,
                'property_id' => $inquiry->property_id,
            ]);

            return $inquiry;
        });
    }

    /** @return array{subscription: NewsletterSubscription, created: bool, already_subscribed: bool} */
    public function subscribe(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $subscription = NewsletterSubscription::firstOrNew(['email' => $data['email']]);
            $created = ! $subscription->exists;
            $wasSubscribed = $subscription->exists && $subscription->status === 'subscribed';

            $subscription->fill([
                'status' => 'subscribed',
                'subscribed_at' => $wasSubscribed ? $subscription->subscribed_at : now(),
                'unsubscribed_at' => null,
                'source' => 'website_footer',
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ])->save();

            if (! $wasSubscribed) {
                $this->audit(null, 'newsletter.subscribed', $subscription, ['source' => $subscription->source]);
            }

            return [
                'subscription' => $subscription,
                'created' => $created,
                'already_subscribed' => $wasSubscribed,
            ];
        });
    }

    public function updateInquiryStatus(ContactInquiry $inquiry, string $status, User $actor): ContactInquiry
    {
        abort_unless($actor->can('inquiries.manage'), 403);
        abort_unless(in_array($status, ['new', 'in_progress', 'resolved', 'spam'], true), 422);

        $before = $inquiry->status;
        $inquiry->update([
            'status' => $status,
            'assigned_to' => in_array($status, ['in_progress', 'resolved'], true) ? $actor->id : $inquiry->assigned_to,
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);
        $this->audit($actor, 'inquiries.status_changed', $inquiry, ['from' => $before, 'to' => $status]);

        return $inquiry->refresh();
    }

    public function updateSubscriptionStatus(NewsletterSubscription $subscription, string $status, User $actor): NewsletterSubscription
    {
        abort_unless($actor->can('newsletter.manage'), 403);
        abort_unless(in_array($status, ['subscribed', 'unsubscribed'], true), 422);

        $subscription->update([
            'status' => $status,
            'subscribed_at' => $status === 'subscribed' ? now() : $subscription->subscribed_at,
            'unsubscribed_at' => $status === 'unsubscribed' ? now() : null,
        ]);
        $this->audit($actor, 'newsletter.'.$status, $subscription, []);

        return $subscription->refresh();
    }

    private function inquiryReference(): string
    {
        do {
            $reference = 'INQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (ContactInquiry::where('reference_number', $reference)->exists());

        return $reference;
    }

    private function audit(?User $actor, string $action, object $subject, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
