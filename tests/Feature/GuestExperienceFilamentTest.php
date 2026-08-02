<?php

namespace Tests\Feature;

use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\AuditLog;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestExperienceFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_review_queue_is_property_scoped_and_guest_content_is_immutable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $ownReview = $this->reviewForProperty($manager->property_id, 'Visible review for the assigned hotel.');
        $otherProperty = Property::whereKeyNot($manager->property_id)->firstOrFail();
        $otherReview = $this->reviewForProperty($otherProperty->id, 'Hidden review from a different hotel.');

        Filament::setCurrentPanel(Filament::getPanel('manager'));
        $this->actingAs($manager)
            ->get('/manager/reviews')
            ->assertOk()
            ->assertSee($ownReview->message)
            ->assertDontSee($otherReview->message)
            ->assertSee('Verified stay')
            ->assertSee('Guest review moderation')
            ->assertSee('Pending');

        $this->assertFalse(ReviewResource::canCreate());
        $this->assertFalse(ReviewResource::canEdit($ownReview));
        $this->assertFalse(ReviewResource::canDelete($ownReview));

        $this->get('/manager/reviews/create')->assertNotFound();
        $this->get("/manager/reviews/{$ownReview->id}/edit")->assertNotFound();
    }

    public function test_moderation_requires_a_rejection_reason_and_valid_status_transitions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $review = $this->reviewForProperty($manager->property_id, 'A review awaiting a controlled moderation decision.');

        $this->actingAs($manager)
            ->patchJson("/api/reviews/{$review->id}", ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('moderation_notes');

        $this->actingAs($manager)
            ->patchJson("/api/reviews/{$review->id}", [
                'status' => 'rejected',
                'moderation_notes' => 'Contains personal information that should not be published.',
            ])
            ->assertOk()
            ->assertJsonPath('review.status', 'rejected')
            ->assertJsonPath('review.moderation_notes', 'Contains personal information that should not be published.');

        $this->actingAs($manager)
            ->patchJson("/api/reviews/{$review->id}", ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($manager)
            ->patchJson("/api/reviews/{$review->id}", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('review.status', 'pending')
            ->assertJsonPath('review.moderation_notes', null);

        $this->actingAs($manager)
            ->patchJson("/api/reviews/{$review->id}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('review.status', 'approved');

        $this->get('/')->assertOk()->assertSee($review->message);

        $this->actingAs($manager)
            ->patchJson("/api/reviews/{$review->id}", ['status' => 'pending'])
            ->assertOk();

        $this->get('/')->assertOk()->assertDontSee($review->message);

        $this->assertSame(4, AuditLog::query()
            ->where('action', 'reviews.moderated')
            ->where('subject_id', $review->id)
            ->count());
    }

    public function test_verified_review_uses_booking_property_and_public_submission_cannot_overwrite_guest_identity(): void
    {
        $this->seed(DatabaseSeeder::class);

        $room = Room::where('status', 'available')->firstOrFail();
        $otherProperty = Property::whereKeyNot($room->property_id)->where('status', 'active')->firstOrFail();
        $guest = Guest::create([
            'name' => 'Original Guest Name',
            'email' => 'guest-identity@example.test',
        ]);
        $reservation = Reservation::create([
            'reference_number' => 'MAH-REVIEW-SCOPE-001',
            'guest_id' => $guest->id,
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'booking_type' => 'personal',
            'check_in' => now()->subDays(4)->toDateString(),
            'check_out' => now()->subDays(2)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'status' => 'checked_out',
            'payment_status' => 'paid',
            'estimated_total' => 13600,
            'source' => 'public',
        ]);

        $this->postJson('/api/public/reviews', [
            'property_id' => $otherProperty->id,
            'reference_number' => $reservation->reference_number,
            'guest_name' => 'Attempted Replacement Name',
            'email' => $guest->email,
            'rating' => 5,
            'message' => 'A verified stay remains attached to the property on the booking.',
        ])
            ->assertCreated()
            ->assertJsonPath('review.property_id', $room->property_id)
            ->assertJsonPath('review.verified', true);

        $this->postJson('/api/public/reviews', [
            'property_id' => $otherProperty->id,
            'guest_name' => 'Attempted Replacement Name',
            'email' => $guest->email,
            'rating' => 3,
            'message' => 'An unverified submission cannot overwrite an existing guest profile name.',
        ])
            ->assertCreated()
            ->assertJsonPath('review.property_id', $otherProperty->id)
            ->assertJsonPath('review.verified', false);

        $this->assertDatabaseHas('guests', [
            'id' => $guest->id,
            'name' => 'Original Guest Name',
        ]);
    }

    public function test_unverified_reviews_require_an_active_property(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('status', 'active')->firstOrFail();
        $property->update(['status' => 'inactive']);

        $this->postJson('/api/public/reviews', [
            'property_id' => $property->id,
            'guest_name' => 'Public Guest',
            'email' => 'inactive-property-review@example.test',
            'rating' => 4,
            'message' => 'This review should not enter an inactive hotel queue.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('property_id');
    }

    private function reviewForProperty(int $propertyId, string $message): Review
    {
        $guest = Guest::create([
            'name' => 'Guest '.uniqid(),
            'email' => uniqid('review-', true).'@example.test',
        ]);

        return Review::create([
            'guest_id' => $guest->id,
            'property_id' => $propertyId,
            'rating' => 4,
            'message' => $message,
            'status' => 'pending',
        ]);
    }
}
