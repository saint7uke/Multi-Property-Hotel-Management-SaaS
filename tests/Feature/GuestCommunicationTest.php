<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactInquiries\ContactInquiryResource;
use App\Filament\Resources\NewsletterSubscriptions\NewsletterSubscriptionResource;
use App\Models\ContactInquiry;
use App\Models\NewsletterSubscription;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use App\Services\GuestCommunicationWorkflow;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_forms_are_connected_to_real_public_endpoints(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('data-endpoint="/api/public/contact-inquiries"', false)
            ->assertSee('data-error-for="full_name"', false)
            ->assertSee('data-error-for="stay_type"', false)
            ->assertSee('id="review-form"', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-endpoint="/api/public/newsletter-subscriptions"', false)
            ->assertSee('data-newsletter-feedback', false);
    }

    public function test_general_inquiry_is_validated_normalized_persisted_and_audited(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('status', 'active')->firstOrFail();

        $response = $this->postJson('/api/public/contact-inquiries', [
            'property_id' => $property->id,
            'full_name' => '  Ada   Santos ',
            'email' => ' ADA@EXAMPLE.TEST ',
            'phone' => '+63 917 555 0100',
            'inquiry_type' => 'group_booking',
            'message' => 'We need rooms and a function space for twenty guests.',
            'website' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Thank you. Your inquiry has been sent to our team.')
            ->assertJsonStructure(['reference_number']);

        $inquiry = ContactInquiry::sole();
        $this->assertSame('Ada Santos', $inquiry->full_name);
        $this->assertSame('ada@example.test', $inquiry->email);
        $this->assertSame('new', $inquiry->status);
        $this->assertSame($property->id, $inquiry->property_id);
        $this->assertSame($inquiry->reference_number, $response->json('reference_number'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inquiries.public_submitted',
            'subject_id' => $inquiry->id,
        ]);
    }

    public function test_general_inquiry_rejects_invalid_and_honeypot_submissions(): void
    {
        $this->postJson('/api/public/contact-inquiries', [
            'full_name' => 'A',
            'email' => 'not-an-email',
            'inquiry_type' => 'invalid',
            'message' => 'short',
            'website' => 'spam.example',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'email', 'inquiry_type', 'message', 'website']);

        $this->assertDatabaseCount('contact_inquiries', 0);
    }

    public function test_newsletter_subscription_is_validated_and_duplicate_safe(): void
    {
        $first = $this->postJson('/api/public/newsletter-subscriptions', [
            'email' => ' Guest@Example.Test ',
            'website' => '',
        ]);

        $first->assertCreated()
            ->assertJsonPath('message', 'You are subscribed to Member Getaway Rates.');

        $this->postJson('/api/public/newsletter-subscriptions', [
            'email' => 'guest@example.test',
            'website' => '',
        ])->assertOk()
            ->assertJsonPath('message', 'This email is already subscribed to Member Getaway Rates.');

        $this->assertDatabaseCount('newsletter_subscriptions', 1);
        $this->assertDatabaseHas('newsletter_subscriptions', [
            'email' => 'guest@example.test',
            'status' => 'subscribed',
        ]);
    }

    public function test_rate_and_review_persists_stay_type_in_the_moderation_queue(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('status', 'active')->firstOrFail();

        $this->postJson('/api/public/reviews', [
            'property_id' => $property->id,
            'guest_name' => 'Event Guest',
            'email' => 'event-review@example.test',
            'rating' => 4,
            'stay_type' => 'event_group',
            'message' => 'The event coordination and guest rooms were handled very well.',
            'website' => '',
        ])->assertCreated()
            ->assertJsonPath('review.status', 'pending');

        $review = Review::whereHas('guest', fn ($query) => $query->where('email', 'event-review@example.test'))->sole();
        $this->assertSame('event_group', $review->stay_type);
        $this->assertSame($property->id, $review->property_id);
    }

    public function test_admin_can_manage_communication_queues_and_manager_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $inquiry = ContactInquiry::create([
            'reference_number' => 'INQ-TEST-001',
            'full_name' => 'Queue Guest',
            'email' => 'queue@example.test',
            'inquiry_type' => 'guest_services',
            'message' => 'Please help with a guest service request.',
            'status' => 'new',
        ]);
        $subscription = NewsletterSubscription::create([
            'email' => 'member@example.test',
            'status' => 'subscribed',
            'subscribed_at' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);
        $this->assertTrue(ContactInquiryResource::canViewAny());
        $this->assertTrue(NewsletterSubscriptionResource::canViewAny());
        $this->get('/admin/contact-inquiries')->assertOk()->assertSee('INQ-TEST-001');
        $this->get('/admin/newsletter-subscriptions')->assertOk()->assertSee('member@example.test');

        app(GuestCommunicationWorkflow::class)->updateInquiryStatus($inquiry, 'resolved', $admin);
        app(GuestCommunicationWorkflow::class)->updateSubscriptionStatus($subscription, 'unsubscribed', $admin);
        $this->assertSame('resolved', $inquiry->fresh()->status);
        $this->assertSame('unsubscribed', $subscription->fresh()->status);

        $this->actingAs($manager);
        $this->assertFalse(ContactInquiryResource::canViewAny());
        $this->assertFalse(NewsletterSubscriptionResource::canViewAny());
    }
}
