<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatConversation;
use App\Services\ChatService;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = ChatConversation::query()->find($conversationId);

    return $conversation && app(ChatService::class)->canAccess($user, $conversation);
});
