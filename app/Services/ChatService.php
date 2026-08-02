<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatPresence;
use App\Models\ChatReadState;
use App\Models\ChatTypingState;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChatService
{
    /** @return Collection<int, ChatConversation> */
    public function conversationsFor(User $user): Collection
    {
        $this->ensureActive($user);

        $conversations = collect();

        if ($user->can('chat.global')) {
            $conversations->push(ChatConversation::query()->firstOrCreate(
                ['key' => 'global'],
                ['scope' => 'global', 'created_by' => $user->id],
            ));
        }

        if ($user->can('chat.property') && $user->property_id) {
            $conversations->push(ChatConversation::query()->firstOrCreate(
                ['key' => 'property:'.$user->property_id],
                [
                    'scope' => 'property',
                    'property_id' => $user->property_id,
                    'created_by' => $user->id,
                ],
            ));
        }

        return $conversations->each(fn (ChatConversation $conversation) => $conversation->loadMissing('property'));
    }

    public function canAccess(User $user, ChatConversation $conversation): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        return match ($conversation->scope) {
            'global' => $user->can('chat.global'),
            'property' => $user->can('chat.property')
                && $user->property_id !== null
                && (int) $user->property_id === (int) $conversation->property_id,
            default => false,
        };
    }

    public function authorize(User $user, ChatConversation $conversation): void
    {
        abort_unless($this->canAccess($user, $conversation), 403);
    }

    public function touchPresence(User $user): ChatPresence
    {
        $this->ensureActive($user);

        return ChatPresence::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['last_seen_at' => now()],
        );
    }

    public function markRead(User $user, ChatConversation $conversation, ?ChatMessage $message = null): ChatReadState
    {
        $this->authorize($user, $conversation);

        $message ??= $conversation->messages()->latest('id')->first();

        if ($message) {
            abort_unless((int) $message->conversation_id === (int) $conversation->id, 422);
        }

        $state = ChatReadState::query()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        if ($message && (int) $message->id > (int) ($state->last_read_message_id ?? 0)) {
            $state->last_read_message_id = $message->id;
        }

        $state->read_at = now();
        $state->save();

        return $state;
    }

    public function setTyping(User $user, ChatConversation $conversation, bool $typing): void
    {
        $this->authorize($user, $conversation);

        if (! $typing) {
            ChatTypingState::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->delete();

            return;
        }

        ChatTypingState::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['expires_at' => now()->addSeconds(6)],
        );
    }

    public function participantQuery(ChatConversation $conversation): Builder
    {
        $permission = $conversation->scope === 'property' ? 'chat.property' : 'chat.global';

        return User::query()
            ->where('status', 'active')
            ->when(
                $conversation->scope === 'property',
                fn (Builder $query) => $query->where('property_id', $conversation->property_id),
            )
            ->permission($permission);
    }

    private function ensureActive(User $user): void
    {
        abort_unless($user->status === 'active', 403);
    }
}
