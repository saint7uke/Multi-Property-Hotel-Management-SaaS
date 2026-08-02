<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_channels_follow_role_and_property_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();

        $managerResponse = $this->actingAs($manager)->getJson('/api/chat/conversations');
        $managerResponse->assertOk()->assertJsonCount(2, 'conversations');
        $this->assertEqualsCanonicalizing(
            ['global', 'property'],
            collect($managerResponse->json('conversations'))->pluck('scope')->all(),
        );

        $adminResponse = $this->actingAs($admin)->getJson('/api/chat/conversations');
        $adminResponse->assertOk()->assertJsonCount(1, 'conversations');
        $adminResponse->assertJsonPath('conversations.0.scope', 'global');
    }

    public function test_property_messages_are_visible_only_to_the_same_property(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $receptionist = User::where('email', 'reception@mahotels.test')->firstOrFail();
        $propertyConversation = collect($this->actingAs($manager)
            ->getJson('/api/chat/conversations')
            ->json('conversations'))
            ->firstWhere('scope', 'property');

        $messageResponse = $this->actingAs($manager)->postJson(
            "/api/chat/conversations/{$propertyConversation['id']}/messages",
            ['body' => 'Room 304 needs a late arrival handover.'],
        );
        $messageResponse->assertCreated()->assertJsonPath('message.body', 'Room 304 needs a late arrival handover.');

        $this->actingAs($receptionist)
            ->getJson("/api/chat/conversations/{$propertyConversation['id']}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Room 304 needs a late arrival handover.');

        $cebu = Property::where('slug', 'ma-skyline-cebu')->firstOrFail();
        $otherManager = User::factory()->create([
            'property_id' => $cebu->id,
            'status' => 'active',
        ]);
        $otherManager->assignRole('manager');

        $this->actingAs($otherManager)
            ->getJson("/api/chat/conversations/{$propertyConversation['id']}/messages")
            ->assertForbidden();

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $this->actingAs($admin)
            ->getJson("/api/chat/conversations/{$propertyConversation['id']}/messages")
            ->assertForbidden();
    }

    public function test_global_chat_read_receipts_presence_and_typing_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $global = collect($this->actingAs($admin)->getJson('/api/chat/conversations')->json('conversations'))->first();

        $message = $this->actingAs($admin)->postJson(
            "/api/chat/conversations/{$global['id']}/messages",
            ['body' => 'Operations briefing starts at 3 PM.'],
        )->assertCreated()->json('message');

        $this->actingAs($manager)
            ->postJson("/api/chat/conversations/{$global['id']}/typing", ['typing' => true])
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/api/chat/conversations/{$global['id']}/read", ['message_id' => $message['id']])
            ->assertOk()
            ->assertJsonPath('last_read_message_id', $message['id']);

        $this->actingAs($manager)->postJson('/api/chat/presence')->assertOk();

        $this->actingAs($admin)
            ->getJson("/api/chat/conversations/{$global['id']}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.read_by_count', 1);

        $this->actingAs($admin)
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('conversations.0.typing.0.name', $manager->name);
    }

    public function test_chat_attachments_are_private_and_authorized(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $receptionist = User::where('email', 'reception@mahotels.test')->firstOrFail();
        $propertyConversation = collect($this->actingAs($manager)
            ->getJson('/api/chat/conversations')
            ->json('conversations'))
            ->firstWhere('scope', 'property');

        $message = $this->actingAs($manager)->post(
            "/api/chat/conversations/{$propertyConversation['id']}/messages",
            ['attachment' => UploadedFile::fake()->create('handover.txt', 2, 'text/plain')],
            ['Accept' => 'application/json'],
        )->assertCreated()->json('message');

        $this->actingAs($receptionist)->get($message['attachment']['url'])->assertOk();

        $otherProperty = Property::where('slug', 'ma-skyline-cebu')->firstOrFail();
        $otherManager = User::factory()->create(['property_id' => $otherProperty->id, 'status' => 'active']);
        $otherManager->assignRole('manager');
        $this->actingAs($otherManager)->get($message['attachment']['url'])->assertForbidden();
    }

    public function test_widget_is_shared_across_public_and_filament_layouts_for_authenticated_staff(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();

        $this->get('/')->assertOk()->assertDontSee('data-chat-widget', false);
        $this->actingAs($manager)->get('/')
            ->assertOk()
            ->assertSee('data-chat-widget', false)
            ->assertSee('MALogo.png', false);
        $this->actingAs($manager)->get('/manager')->assertOk()->assertSee('data-chat-widget', false);
    }

    public function test_inactive_accounts_cannot_use_chat(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $manager->update(['status' => 'inactive']);

        $this->actingAs($manager)->getJson('/api/chat/conversations')->assertForbidden();
    }
}
