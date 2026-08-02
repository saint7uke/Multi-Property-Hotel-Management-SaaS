<?php

namespace Tests\Feature;

use App\Models\Property;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelPropertyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_page_uses_premium_layout_without_changing_workflow_contracts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'ma-grand-manila')->firstOrFail();

        $response = $this->get(route('hotels.show', $property));

        $response->assertOk()
            ->assertSee('data-hotel-page', false)
            ->assertSee('data-premium-hero-image', false)
            ->assertSee('data-hotel-gallery', false)
            ->assertSee('data-hotel-gallery-dialog', false)
            ->assertSee('hotel-room-card', false)
            ->assertSee('id="booking-form"', false)
            ->assertSee('name="booking_type"', false)
            ->assertSee('name="property_id"', false)
            ->assertSee('name="room_id"', false)
            ->assertSee('id="status-form"', false)
            ->assertSee('id="review-form"', false)
            ->assertSee($property->name)
            ->assertSee($property->city);
    }
}
