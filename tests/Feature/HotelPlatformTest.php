<?php

namespace Tests\Feature;

use App\Filament\Widgets\HotelOverview;
use App\Filament\Widgets\RecentReservations;
use App\Filament\Widgets\ReservationStatusChart;
use App\Filament\Widgets\RoomReadinessChart;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HotelPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_availability_returns_seeded_rooms(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::firstOrFail();

        $response = $this->getJson('/api/public/rooms/availability?'.http_build_query([
            'property_id' => $property->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => 2,
        ]));

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'room_number', 'type', 'rate', 'property']]]);
    }

    public function test_public_booking_submission_creates_pending_reservation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $room = Room::query()->where('status', 'available')->firstOrFail();

        $response = $this->postJson('/api/public/bookings', [
            'booking_type' => 'personal',
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'guest_name' => 'Nina Cruz',
            'email' => 'nina@example.test',
            'phone' => '+63 917 555 0198',
            'home_address' => '123 Mabini Street, Manila',
            'check_in' => now()->addDays(14)->toDateString(),
            'check_out' => now()->addDays(16)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'room_count' => 2,
            'preferred_area' => 'Manila',
            'wants_breakfast' => true,
            'addons' => ['extra_bed', 'late_check_out'],
            'payment_method' => 'gcash',
            'terms_accepted' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'pending');

        $this->assertDatabaseHas('reservations', [
            'status' => 'pending',
            'source' => 'public',
            'room_count' => 2,
            'preferred_area' => 'Manila',
            'wants_breakfast' => true,
            'payment_method' => 'gcash',
        ]);

        $this->assertDatabaseHas('guests', [
            'email' => 'nina@example.test',
            'address' => '123 Mabini Street, Manila',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reservations.public_submitted',
            'subject_id' => $response->json('reservation.id'),
        ]);

        $referenceNumber = $response->json('reservation.reference_number');
        $typedReference = strtolower(str_replace('-', ' - ', $referenceNumber));

        $lookupResponse = $this->postJson('/api/public/booking/lookup', [
            'reference_number' => $typedReference,
            'email' => 'NINA@example.test',
        ]);

        $lookupResponse->assertOk()
            ->assertJsonPath('reservation.reference_number', $referenceNumber)
            ->assertJsonPath('reservation.booking_type', 'personal')
            ->assertJsonPath('reservation.guest_name', 'Nina Cruz')
            ->assertJsonPath('reservation.status', 'pending')
            ->assertJsonPath('reservation.payment_status', 'pending')
            ->assertJsonPath('reservation.room_count', 2)
            ->assertJsonPath('reservation.wants_breakfast', true)
            ->assertJsonPath('reservation.payment_method', 'gcash')
            ->assertJsonPath('reservation.room.room_number', $room->room_number);

        $wrongEmailResponse = $this->postJson('/api/public/booking/lookup', [
            'reference_number' => $referenceNumber,
            'email' => 'someone-else@example.test',
        ]);

        $wrongEmailResponse->assertNotFound()
            ->assertJsonPath('message', 'No booking matched that reference number and email.');
    }

    public function test_public_event_booking_keeps_selected_property_scope(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'ma-skyline-cebu')->firstOrFail();

        $response = $this->postJson('/api/public/bookings', [
            'booking_type' => 'event',
            'property_id' => $property->id,
            'event_name' => 'Regional planning retreat',
            'guest_name' => 'Mika Reyes',
            'email' => 'mika@example.test',
            'phone' => '+63 917 555 0123',
            'home_address' => 'Cebu Business Park',
            'check_in' => now()->addDays(20)->toDateString(),
            'check_out' => now()->addDays(22)->toDateString(),
            'adults' => 8,
            'children' => 0,
            'room_count' => 4,
            'preferred_area' => 'Cebu',
            'payment_method' => 'bank_transfer',
            'terms_accepted' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('reservation.property_id', $property->id)
            ->assertJsonPath('reservation.booking_type', 'event');

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $manager->forceFill(['property_id' => $property->id])->save();

        $listResponse = $this->actingAs($manager)->getJson('/api/reservations?property_id='.$property->id);

        $listResponse->assertOk();
        $this->assertContains($response->json('reservation.id'), collect($listResponse->json('data'))->pluck('id')->all());

        $filteredResponse = $this->actingAs($manager)->getJson('/api/reservations?source=public&status=pending&booking_type=event&search=Regional');

        $filteredResponse->assertOk();
        $this->assertContains($response->json('reservation.id'), collect($filteredResponse->json('data'))->pluck('id')->all());

        $lookupResponse = $this->postJson('/api/public/booking/lookup', [
            'reference_number' => $response->json('reservation.reference_number'),
            'email' => 'mika@example.test',
        ]);

        $lookupResponse->assertOk()
            ->assertJsonPath('reservation.booking_type', 'event')
            ->assertJsonPath('reservation.event_name', 'Regional planning retreat')
            ->assertJsonPath('reservation.property.name', $property->name)
            ->assertJsonPath('reservation.room', null)
            ->assertJsonPath('reservation.next_step', 'Our team is reviewing the event or group request and will confirm pricing.');
    }

    public function test_public_hotel_slug_page_renders_active_property_and_scoped_forms(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'ma-skyline-singapore')->firstOrFail();

        $homeResponse = $this->get('/');

        $homeResponse->assertOk()
            ->assertSee(route('hotels.show', $property), false);

        $response = $this->get('/hotels/'.$property->slug);

        $response->assertOk()
            ->assertSee($property->name)
            ->assertSee('Book '.$property->name)
            ->assertSee('value="'.$property->id.'" selected', false)
            ->assertSee('id="booking-form"', false)
            ->assertSee('id="review-form"', false)
            ->assertSee('id="status-form"', false);

        $property->update(['status' => 'inactive']);

        $this->get('/hotels/'.$property->slug)->assertNotFound();
    }

    public function test_public_landing_pages_render_prompt_content_and_forms(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'ma-grand-manila')->firstOrFail();

        $this->get('/')
            ->assertOk()
            ->assertSee('Premium Hospitality')
            ->assertSee('Relaxing Amenities')
            ->assertSee('Guest Reviews')
            ->assertSee('font-sans', false)
            ->assertSee('font-display', false)
            ->assertSee(route('book.now'), false);

        $this->get('/about')
            ->assertOk()
            ->assertSee('M.A Group of Hotels - About Us')
            ->assertSee('Mission')
            ->assertSee('Cebu Pacific')
            ->assertSee('Ready to experience M.A hospitality?');

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Stories &amp; Guest Guide - Hotel Journal', false)
            ->assertSee('Room Reservation Guide for First-Time Guests')
            ->assertSee('Featured Stay');

        $this->get('/contact')
            ->assertOk()
            ->assertSeeText('Contact Us', false)
            ->assertSee('id="inquiry-form"', false)
            ->assertSee('id="review-form"', false)
            ->assertSee($property->name);

        $this->get('/book-now')
            ->assertOk()
            ->assertSee('Booking Request')
            ->assertSee('Personal / Room Booking')
            ->assertSee('Events / Group Booking')
            ->assertSee('id="booking-form"', false)
            ->assertSee('id="status-form"', false)
            ->assertSee($property->name);
    }

    public function test_public_booking_rejects_room_from_wrong_property(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'ma-grand-manila')->firstOrFail();
        $otherRoom = Room::where('property_id', '!=', $property->id)->firstOrFail();

        $response = $this->postJson('/api/public/bookings', [
            'booking_type' => 'personal',
            'property_id' => $property->id,
            'room_id' => $otherRoom->id,
            'guest_name' => 'Nina Cruz',
            'email' => 'wrong-room@example.test',
            'phone' => '+63 917 555 0198',
            'home_address' => '123 Mabini Street, Manila',
            'check_in' => now()->addDays(14)->toDateString(),
            'check_out' => now()->addDays(16)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'payment_method' => 'gcash',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('room_id');
    }

    public function test_manager_room_search_does_not_leak_other_properties(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $otherProperty = Property::where('id', '!=', $manager->property_id)->firstOrFail();

        Room::create([
            'property_id' => $otherProperty->id,
            'room_number' => 'LEAK-500',
            'type' => 'Leak Proof Suite',
            'rate' => 9999,
            'capacity' => 2,
            'status' => 'available',
        ]);

        $response = $this->actingAs($manager)->getJson('/api/rooms?search=Leak');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_manage_properties_and_manager_cannot_create_new_property(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();

        $createResponse = $this->actingAs($admin)->postJson('/api/properties', [
            'name' => ' M&A Harbour Davao ',
            'slug' => ' M&A Harbour DAVAO !!! ',
            'address' => ' Lanang Business District ',
            'city' => ' Davao ',
            'country' => ' Philippines ',
            'status' => 'ACTIVE',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('property.name', 'M&A Harbour Davao')
            ->assertJsonPath('property.slug', 'm-and-a-harbour-davao')
            ->assertJsonPath('property.status', 'active');

        $propertyId = $createResponse->json('property.id');

        $this->assertDatabaseHas('properties', [
            'id' => $propertyId,
            'slug' => 'm-and-a-harbour-davao',
            'status' => 'active',
        ]);

        $updateResponse = $this->actingAs($admin)->putJson('/api/properties/'.$propertyId, [
            'name' => 'M&A Harbour Davao Bay',
            'slug' => '',
            'address' => ' Lanang Waterfront ',
            'city' => 'Davao',
            'country' => 'Philippines',
            'status' => 'INACTIVE',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('property.slug', 'm-and-a-harbour-davao-bay')
            ->assertJsonPath('property.status', 'inactive')
            ->assertJsonPath('property.address', 'Lanang Waterfront');

        $duplicateResponse = $this->actingAs($admin)->putJson('/api/properties/'.$propertyId, [
            'name' => 'M&A Harbour Davao Bay',
            'slug' => 'MA Grand Manila',
            'address' => 'Lanang Waterfront',
            'city' => 'Davao',
            'country' => 'Philippines',
            'status' => 'inactive',
        ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();

        $managerListResponse = $this->actingAs($manager)->getJson('/api/properties');

        $managerListResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $manager->property_id);

        $managerCreateResponse = $this->actingAs($manager)->postJson('/api/properties', [
            'name' => 'M&A Hidden Baguio',
            'slug' => 'ma-hidden-baguio',
            'address' => 'Session Road',
            'city' => 'Baguio',
            'country' => 'Philippines',
            'status' => 'active',
        ]);

        $managerCreateResponse->assertForbidden();
    }

    public function test_manager_cannot_create_reservation_for_other_property_room(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $otherRoom = Room::where('property_id', '!=', $manager->property_id)->firstOrFail();

        $response = $this->actingAs($manager)->postJson('/api/reservations', [
            'guest_name' => 'Cross Scope Guest',
            'email' => 'cross-scope@example.test',
            'phone' => '+63 917 555 0101',
            'room_id' => $otherRoom->id,
            'booking_type' => 'personal',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 2,
            'children' => 0,
        ]);

        $response->assertForbidden();
    }

    public function test_manager_can_create_event_reservation_without_room(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();

        $response = $this->actingAs($manager)->postJson('/api/reservations', [
            'guest_name' => 'Event Client',
            'email' => 'event-client@example.test',
            'phone' => '+63 917 555 0103',
            'property_id' => $manager->property_id,
            'booking_type' => 'event',
            'event_name' => 'Leadership Planning Dinner',
            'check_in' => now()->addDays(18)->toDateString(),
            'check_out' => now()->addDays(19)->toDateString(),
            'adults' => 12,
            'children' => 0,
        ]);

        $response->assertCreated()
            ->assertJsonPath('reservation.booking_type', 'event')
            ->assertJsonPath('reservation.property_id', $manager->property_id)
            ->assertJsonPath('reservation.room_id', null)
            ->assertJsonPath('reservation.status', 'pending')
            ->assertJsonPath('reservation.estimated_total', '0.00');

        $otherProperty = Property::where('id', '!=', $manager->property_id)->firstOrFail();

        $forbiddenResponse = $this->actingAs($manager)->postJson('/api/reservations', [
            'guest_name' => 'Other Property Event',
            'email' => 'other-event@example.test',
            'phone' => '+63 917 555 0104',
            'property_id' => $otherProperty->id,
            'booking_type' => 'event',
            'event_name' => 'Cross Property Dinner',
            'check_in' => now()->addDays(18)->toDateString(),
            'check_out' => now()->addDays(19)->toDateString(),
            'adults' => 12,
            'children' => 0,
        ]);

        $forbiddenResponse->assertForbidden();
    }

    public function test_staff_personal_booking_uses_available_non_overlapping_rooms(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $room = Room::where('property_id', $manager->property_id)->where('status', 'available')->firstOrFail();
        $guest = Guest::create([
            'name' => 'Overlap Guest',
            'email' => 'overlap@example.test',
            'phone' => '+63 917 555 0105',
        ]);
        $checkIn = now()->addDays(25)->toDateString();
        $checkOut = now()->addDays(27)->toDateString();

        Reservation::create([
            'reference_number' => 'MAH-OVERLAP-001',
            'guest_id' => $guest->id,
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'booking_type' => 'personal',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'estimated_total' => 12000,
            'source' => 'public',
        ]);

        $availabilityResponse = $this->actingAs($manager)->getJson('/api/rooms?'.http_build_query([
            'property_id' => $manager->property_id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
            'available' => 1,
            'per_page' => 80,
        ]));

        $availabilityResponse->assertOk();
        $this->assertNotContains($room->id, collect($availabilityResponse->json('data'))->pluck('id')->all());

        $response = $this->actingAs($manager)->postJson('/api/reservations', [
            'guest_name' => 'Blocked Overlap',
            'email' => 'blocked-overlap@example.test',
            'phone' => '+63 917 555 0106',
            'room_id' => $room->id,
            'booking_type' => 'personal',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 2,
            'children' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('room_id');
    }

    public function test_manager_cannot_update_other_property_reservation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $otherRoom = Room::where('property_id', '!=', $manager->property_id)->firstOrFail();
        $guest = Guest::create([
            'name' => 'Other Property Guest',
            'email' => 'other-property@example.test',
            'phone' => '+63 917 555 0102',
        ]);

        $reservation = Reservation::create([
            'reference_number' => 'MAH-SCOPE-001',
            'guest_id' => $guest->id,
            'property_id' => $otherRoom->property_id,
            'room_id' => $otherRoom->id,
            'booking_type' => 'personal',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'status' => 'pending',
            'payment_status' => 'pending',
            'estimated_total' => 12000,
            'source' => 'public',
        ]);

        $response = $this->actingAs($manager)->patchJson("/api/reservations/{$reservation->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_review_moderation_is_property_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $otherProperty = Property::where('id', '!=', $manager->property_id)->firstOrFail();
        $guest = Guest::create([
            'name' => 'Review Guest',
            'email' => 'review-scope@example.test',
        ]);

        $review = Review::create([
            'guest_id' => $guest->id,
            'property_id' => $otherProperty->id,
            'rating' => 4,
            'message' => 'This should stay outside the manager scope.',
            'status' => 'pending',
        ]);

        $listResponse = $this->actingAs($manager)->getJson('/api/reviews');
        $listResponse->assertOk();
        $this->assertNotContains($review->id, collect($listResponse->json('data'))->pluck('id')->all());

        $updateResponse = $this->actingAs($manager)->patchJson("/api/reviews/{$review->id}", [
            'status' => 'approved',
        ]);

        $updateResponse->assertForbidden();
    }

    public function test_public_property_review_enters_property_scoped_moderation_queue(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $property = Property::findOrFail($manager->property_id);

        $response = $this->postJson('/api/public/reviews', [
            'property_id' => $property->id,
            'guest_name' => 'Review Guest',
            'email' => 'public-review@example.test',
            'rating' => 5,
            'message' => 'The front desk team made the stay easy and memorable.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('review.property_id', $property->id)
            ->assertJsonPath('review.status', 'pending')
            ->assertJsonPath('review.verified', false);

        $listResponse = $this->actingAs($manager)->getJson('/api/reviews');

        $listResponse->assertOk();
        $this->assertContains($response->json('review.id'), collect($listResponse->json('data'))->pluck('id')->all());

        $filteredResponse = $this->actingAs($manager)->getJson('/api/reviews?'.http_build_query([
            'status' => 'pending',
            'verified' => 'unverified',
            'search' => 'front desk',
        ]));

        $filteredResponse->assertOk();
        $this->assertContains($response->json('review.id'), collect($filteredResponse->json('data'))->pluck('id')->all());

        $approveResponse = $this->actingAs($manager)->patchJson('/api/reviews/'.$response->json('review.id'), [
            'status' => 'approved',
        ]);

        $approveResponse->assertOk()
            ->assertJsonPath('review.status', 'approved')
            ->assertJsonPath('review.moderated_by.name', $manager->name);

        $pendingResponse = $this->actingAs($manager)->getJson('/api/reviews?status=pending');

        $pendingResponse->assertOk();
        $this->assertNotContains($response->json('review.id'), collect($pendingResponse->json('data'))->pluck('id')->all());

        $approvedResponse = $this->actingAs($manager)->getJson('/api/reviews?'.http_build_query([
            'status' => 'approved',
            'search' => 'public-review@example.test',
        ]));

        $approvedResponse->assertOk();
        $this->assertContains($response->json('review.id'), collect($approvedResponse->json('data'))->pluck('id')->all());

        $reopenResponse = $this->actingAs($manager)->patchJson('/api/reviews/'.$response->json('review.id'), [
            'status' => 'pending',
        ]);

        $reopenResponse->assertOk()
            ->assertJsonPath('review.status', 'pending');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reviews.public_submitted',
            'subject_id' => $response->json('review.id'),
        ]);
    }

    public function test_public_verified_review_requires_matching_checked_out_booking(): void
    {
        $this->seed(DatabaseSeeder::class);

        $room = Room::where('status', 'available')->firstOrFail();
        $guest = Guest::create([
            'name' => 'Verified Guest',
            'email' => 'verified-review@example.test',
            'phone' => '+63 917 555 0110',
        ]);

        $reservation = Reservation::create([
            'reference_number' => 'MAH-VERIFY-001',
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

        $wrongEmailResponse = $this->postJson('/api/public/reviews', [
            'reference_number' => 'mah - verify - 001',
            'guest_name' => 'Verified Guest',
            'email' => 'wrong-verified@example.test',
            'rating' => 5,
            'message' => 'This should not attach to somebody else booking.',
        ]);

        $wrongEmailResponse->assertStatus(422)
            ->assertJsonValidationErrors('reference_number');

        $response = $this->postJson('/api/public/reviews', [
            'reference_number' => 'mah - verify - 001',
            'guest_name' => 'Verified Guest',
            'email' => 'VERIFIED-REVIEW@example.test',
            'rating' => 5,
            'message' => 'A properly verified stay review after checkout.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('review.reservation_id', $reservation->id)
            ->assertJsonPath('review.property_id', $room->property_id)
            ->assertJsonPath('review.verified', true);

        $duplicateResponse = $this->postJson('/api/public/reviews', [
            'reference_number' => 'MAH-VERIFY-001',
            'guest_name' => 'Verified Guest',
            'email' => 'verified-review@example.test',
            'rating' => 4,
            'message' => 'Duplicate reviews for the same booking should wait.',
        ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonValidationErrors('reference_number');
    }

    public function test_central_login_session_can_receive_the_staff_rbac_payload(): void
    {
        $this->seed(DatabaseSeeder::class);

        $loginResponse = $this->post('/staff/sign-in', [
            'email' => 'admin@mahotels.test',
            'password' => 'password',
        ]);
        $loginResponse->assertRedirect('/admin');

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@mahotels.test')
            ->assertJsonPath('user.roles.0', 'admin');

        $this->assertContains('dashboard.view', $response->json('user.permissions'));
        $this->assertNotNull(User::where('email', 'admin@mahotels.test')->value('last_login_at'));
        $this->postJson('/api/auth/login', [
            'email' => 'admin@mahotels.test',
            'password' => 'password',
        ])->assertNotFound();
    }

    public function test_admin_can_access_dashboard_summary(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonStructure(['stats' => ['pending_reservations', 'pending_public_reservations', 'confirmed_reservations', 'occupancy_rate', 'revenue']])
            ->assertJsonPath('stats.pending_public_reservations', 1);
    }

    public function test_report_filters_include_overlapping_stays_and_payment_dates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $reservation = Reservation::where('reference_number', 'MAH-DEMO-001')->firstOrFail();
        $reservation->update([
            'check_in' => today()->subDay(),
            'check_out' => today()->addDay(),
            'status' => 'confirmed',
        ]);
        $reservation->payments()->firstOrFail()->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        Room::where('property_id', $reservation->property_id)->update(['status' => 'occupied']);

        $response = $this->actingAs($admin)->getJson('/api/reports/summary?'.http_build_query([
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'property_id' => $reservation->property_id,
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.reservations', 1)
            ->assertJsonPath('summary.confirmed', 1)
            ->assertJsonPath('summary.revenue', (int) $reservation->payments()->firstOrFail()->amount)
            ->assertJsonPath('summary.occupancy_rate', 100);
    }

    public function test_staff_can_only_access_their_dedicated_filament_panel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $receptionist = User::where('email', 'reception@mahotels.test')->firstOrFail();
        $housekeeper = User::where('email', 'housekeeping@mahotels.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('System overview');
        $this->actingAs($manager)->get('/manager')->assertOk()->assertSee('Property overview');
        $this->actingAs($receptionist)->get('/receptionist')->assertOk()->assertSee('Front desk');
        $this->actingAs($housekeeper)->get('/housekeeping')->assertOk()->assertSee('Housekeeping board');

        $this->actingAs($manager)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/manager')->assertForbidden();
        $this->actingAs($receptionist)->get('/manager')->assertForbidden();
        $this->actingAs($housekeeper)->get('/receptionist')->assertForbidden();

        $this->actingAs($manager)->get('/manager/users')->assertOk();
        $this->actingAs($receptionist)->get('/receptionist/reservations')->assertOk();
        $this->actingAs($housekeeper)->get('/housekeeping/housekeeping-tasks')->assertOk();

        $otherProperty = Property::where('id', '!=', $manager->property_id)->firstOrFail();
        $this->actingAs($manager)
            ->get('/manager/properties')
            ->assertOk()
            ->assertSee($manager->property->name)
            ->assertDontSee($otherProperty->name);

        $this->actingAs($receptionist)->get('/receptionist/users')->assertNotFound();
        $this->actingAs($housekeeper)->get('/housekeeping/reservations')->assertNotFound();
    }

    public function test_staff_sign_in_is_centralized_for_every_panel(): void
    {
        $this->get('/staff/sign-in')
            ->assertOk()
            ->assertSee('One secure entrance')
            ->assertSee('action="'.route('staff.authenticate').'"', false)
            ->assertDontSee('/manager/login', false);

        $this->get('/staff/login')->assertRedirect('/staff/sign-in');

        foreach (['admin', 'manager', 'receptionist', 'housekeeping'] as $panel) {
            $this->get("/{$panel}")->assertRedirect('/staff/sign-in');
            $this->get("/{$panel}/login")->assertNotFound();
        }
    }

    public function test_central_staff_login_redirects_each_role_and_logout_returns_to_sign_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            'admin@mahotels.test' => '/admin',
            'manager@mahotels.test' => '/manager',
            'reception@mahotels.test' => '/receptionist',
            'housekeeping@mahotels.test' => '/housekeeping',
        ];

        foreach ($accounts as $email => $panelPath) {
            $user = User::where('email', $email)->firstOrFail();

            $this->post('/staff/sign-in', [
                'email' => $email,
                'password' => 'password',
            ])->assertRedirect($panelPath);

            $this->assertAuthenticatedAs($user);
            $this->assertNotNull($user->fresh()->last_login_at);
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $user->id,
                'action' => 'auth.login_succeeded',
            ]);

            $this->post("{$panelPath}/logout")
                ->assertRedirect('/staff/sign-in');
            $this->assertGuest();
        }
    }

    public function test_central_staff_login_uses_generic_failures_and_rejects_inactive_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->from('/staff/sign-in')->post('/staff/sign-in', [
            'email' => 'unknown-staff@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect('/staff/sign-in')
            ->assertSessionHasErrors(['email' => 'The sign-in details could not be verified.']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'action' => 'auth.login_failed',
        ]);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $manager->update(['status' => 'inactive']);

        $this->from('/staff/sign-in')->post('/staff/sign-in', [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect('/staff/sign-in')
            ->assertSessionHasErrors(['email' => 'The sign-in details could not be verified.']);

        $this->assertGuest();
    }

    public function test_central_staff_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/staff/sign-in', [
                'email' => 'rate-limit@example.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/staff/sign-in', [
            'email' => 'rate-limit@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_authenticated_staff_sign_in_redirects_to_their_panel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();

        $this->actingAs($manager)->get('/staff/sign-in')->assertRedirect('/manager');
    }

    public function test_filament_dashboard_statistics_follow_staff_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();

        $this->actingAs($admin);
        Livewire::test(HotelOverview::class)
            ->assertSee('Arrivals today')
            ->assertSee('Pending requests')
            ->assertSee('Current occupancy')
            ->assertSee('Revenue this month');
        Livewire::test(ReservationStatusChart::class)->assertSee('Reservation pipeline');
        Livewire::test(RoomReadinessChart::class)->assertSee('Room readiness');
        Livewire::test(RecentReservations::class)
            ->assertSee('Recent bookings')
            ->assertSee('MAH-DEMO-001');

        $housekeeper = User::where('email', 'housekeeping@mahotels.test')->firstOrFail();

        Filament::setCurrentPanel(Filament::getPanel('housekeeping'));
        $this->actingAs($housekeeper);
        Livewire::test(HotelOverview::class)
            ->assertSee('Current occupancy')
            ->assertSee('Rooms needing attention')
            ->assertDontSee('Pending requests')
            ->assertDontSee('Revenue this month');
    }

    public function test_filament_reports_render_with_property_scoped_rbac(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Bookings in period')
            ->assertSee('Room-night occupancy')
            ->assertSee('Property performance')
            ->assertSee('M&amp;A Skyline Cebu', false)
            ->assertSee('Export CSV');

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();

        $this->actingAs($manager)
            ->get('/manager/reports')
            ->assertOk()
            ->assertSee($manager->property->name)
            ->assertDontSee('M&amp;A Skyline Cebu', false)
            ->assertSee('/manager/reports/reservations.csv', false);

        $this->actingAs($manager)->get('/admin/reports')->assertForbidden();

        $housekeeper = User::where('email', 'housekeeping@mahotels.test')->firstOrFail();

        $this->actingAs($housekeeper)->get('/housekeeping/reports')->assertNotFound();
    }

    public function test_admin_can_create_manager_for_selected_property(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $property = Property::where('slug', 'ma-skyline-cebu')->firstOrFail();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Cebu Operations Manager',
            'email' => 'cebu.manager@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'role' => 'manager',
            'property_id' => $property->id,
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'cebu.manager@example.test')
            ->assertJsonPath('user.roles.0', 'manager')
            ->assertJsonPath('user.property.id', $property->id);

        $created = User::where('email', 'cebu.manager@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('manager'));
        $this->assertSame($property->id, $created->property_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'users.created',
            'subject_id' => $created->id,
        ]);
    }

    public function test_manager_can_only_manage_staff_for_their_own_property(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $ownProperty = $manager->property;
        $otherProperty = Property::where('id', '!=', $ownProperty->id)->firstOrFail();

        $ownResponse = $this->actingAs($manager)->postJson('/api/users', [
            'name' => 'Manila Assistant Manager',
            'email' => 'manila.assistant@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'role' => 'manager',
            'property_id' => $ownProperty->id,
            'status' => 'active',
        ]);

        $ownResponse->assertCreated()
            ->assertJsonPath('user.property.id', $ownProperty->id);

        $otherResponse = $this->actingAs($manager)->postJson('/api/users', [
            'name' => 'Cebu Assistant Manager',
            'email' => 'cebu.assistant@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'role' => 'manager',
            'property_id' => $otherProperty->id,
            'status' => 'active',
        ]);

        $otherResponse->assertStatus(422)
            ->assertJsonValidationErrors('property_id');

        $adminResponse = $this->actingAs($manager)->postJson('/api/users', [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized.admin@example.test',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'role' => 'admin',
            'property_id' => null,
            'status' => 'active',
        ]);

        $adminResponse->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $listResponse = $this->actingAs($manager)->getJson('/api/users');

        $listResponse->assertOk();
        $propertyIds = collect($listResponse->json('data'))->pluck('property_id')->filter()->unique()->values()->all();
        $this->assertSame([$ownProperty->id], $propertyIds);
    }

    public function test_admin_property_cms_edits_render_on_the_public_hotel_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $property = Property::where('slug', 'ma-grand-manila')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/properties/'.$property->id.'/edit')
            ->assertOk()
            ->assertSee('Property CMS')
            ->assertSee('Public page')
            ->assertSee('Hero image')
            ->assertSee('Guest information')
            ->assertSee('Search appearance');

        $property->update([
            'tagline' => 'A calm Manila stay for business and family travel.',
            'description' => 'A centrally located property with thoughtful service and flexible booking support.',
            'amenities' => ['Swimming pool', 'Breakfast service'],
            'highlights' => [
                ['title' => 'Central location', 'description' => 'Convenient access to key Manila destinations.'],
            ],
            'contact_email' => 'manila@magroupofhotels.test',
            'contact_phone' => '+63 2 8123 4567',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'meta_title' => 'M.A Grand Manila Hotel',
            'meta_description' => 'Stay at M.A Grand Manila for comfortable rooms and direct booking support.',
        ]);

        $this->get('/hotels/'.$property->slug)
            ->assertOk()
            ->assertSee('M.A Grand Manila Hotel')
            ->assertSee('A calm Manila stay for business and family travel.')
            ->assertSee('A centrally located property with thoughtful service')
            ->assertSee('Swimming pool')
            ->assertSee('Central location')
            ->assertSee('manila@magroupofhotels.test')
            ->assertSee('2:00 PM');
    }
}
