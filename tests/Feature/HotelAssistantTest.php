<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HotelAssistantContent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_assistant_renders_configured_faqs_and_actions(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-hotel-assistant', false)
            ->assertSee('How can I book a room?')
            ->assertSee('Do you accept group or event bookings?')
            ->assertSee('What are your contact details?')
            ->assertSee('What are your business hours?')
            ->assertSee('"label":"Book a Room"', false)
            ->assertSee('"label":"View Rooms"', false)
            ->assertSee('"label":"Contact Us"', false);
    }

    public function test_assistant_is_available_across_public_pages(): void
    {
        foreach (['/about', '/blog', '/contact', '/book-now'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-hotel-assistant', false);
        }
    }

    public function test_authenticated_staff_see_internal_chat_instead_of_guest_assistant(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();

        $this->actingAs($manager)
            ->get('/')
            ->assertOk()
            ->assertDontSee('data-hotel-assistant', false)
            ->assertSee('data-chat-widget', false);
    }

    public function test_content_provider_resolves_route_configuration_for_the_frontend(): void
    {
        $payload = app(HotelAssistantContent::class)->payload();

        $this->assertCount(4, $payload['faqs']);
        $this->assertSame(route('book.now').'#booking', $payload['quickActions'][0]['url']);
        $this->assertSame('show-faqs', $payload['quickActions'][3]['action']);
        $this->assertSame(route('contact'), $payload['staffAction']['url']);
    }
}
