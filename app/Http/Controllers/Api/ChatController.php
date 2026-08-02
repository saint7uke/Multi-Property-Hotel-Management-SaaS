<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Events\ChatStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatPresence;
use App\Models\ChatReadState;
use App\Models\ChatTypingState;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatController extends Controller
{
    public function conversations(Request $request, ChatService $chat): JsonResponse
    {
        $chat->touchPresence($request->user());

        return response()->json([
            'conversations' => $chat->conversationsFor($request->user())
                ->map(fn (ChatConversation $conversation) => $this->conversationPayload($conversation, $request->user()->id, $chat))
                ->values(),
        ]);
    }

    public function messages(Request $request, ChatConversation $conversation, ChatService $chat): JsonResponse
    {
        $chat->authorize($request->user(), $conversation);

        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = $conversation->messages()->with('user:id,name')->orderBy('id');

        if (isset($validated['after_id'])) {
            $messages = $query->where('id', '>', $validated['after_id'])->limit(100)->get();
        } else {
            $messages = $conversation->messages()
                ->with('user:id,name')
                ->latest('id')
                ->limit(50)
                ->get()
                ->sortBy('id')
                ->values();
        }

        $readPositions = ChatReadState::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $request->user()->id)
            ->pluck('last_read_message_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        $onlineUserIds = ChatPresence::query()
            ->where('last_seen_at', '>=', now()->subSeconds(75))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $message) => $this->messagePayload(
                $message,
                $request->user()->id,
                $readPositions->filter(fn (int $position) => $position >= $message->id)->count(),
                in_array((int) $message->user_id, $onlineUserIds, true),
            ))->values(),
        ]);
    }

    public function store(Request $request, ChatConversation $conversation, ChatService $chat): JsonResponse
    {
        $chat->authorize($request->user(), $conversation);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4000', 'required_without:attachment'],
            'attachment' => [
                'nullable',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt',
                'required_without:body',
            ],
        ]);

        $attachment = $request->file('attachment');
        $attachmentPath = $attachment?->store('chat-attachments/'.$conversation->id, 'local');

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => filled($validated['body'] ?? null) ? trim($validated['body']) : null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachment ? Str::limit($attachment->getClientOriginalName(), 240, '') : null,
            'attachment_mime' => $attachment?->getMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ])->load('user:id,name');

        $chat->markRead($request->user(), $conversation, $message);
        $chat->setTyping($request->user(), $conversation, false);

        $payload = $this->messagePayload($message, $request->user()->id, 0, true);
        broadcast(new ChatMessageSent($conversation->id, $payload))->toOthers();

        return response()->json(['message' => $payload], 201);
    }

    public function read(Request $request, ChatConversation $conversation, ChatService $chat): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['nullable', 'integer', 'exists:chat_messages,id'],
        ]);

        $message = isset($validated['message_id'])
            ? ChatMessage::query()->findOrFail($validated['message_id'])
            : null;

        $state = $chat->markRead($request->user(), $conversation, $message);
        broadcast(new ChatStateUpdated($conversation->id, 'read'))->toOthers();

        return response()->json(['last_read_message_id' => $state->last_read_message_id]);
    }

    public function typing(Request $request, ChatConversation $conversation, ChatService $chat): JsonResponse
    {
        $validated = $request->validate(['typing' => ['required', 'boolean']]);
        $chat->setTyping($request->user(), $conversation, $validated['typing']);
        broadcast(new ChatStateUpdated($conversation->id, 'typing'))->toOthers();

        return response()->json(['typing' => $validated['typing']]);
    }

    public function presence(Request $request, ChatService $chat): JsonResponse
    {
        $presence = $chat->touchPresence($request->user());

        return response()->json(['last_seen_at' => $presence->last_seen_at->toIso8601String()]);
    }

    public function attachment(Request $request, ChatMessage $message, ChatService $chat): BinaryFileResponse
    {
        $message->loadMissing('conversation');
        $chat->authorize($request->user(), $message->conversation);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        $disposition = str_starts_with((string) $message->attachment_mime, 'image/') ? 'inline' : 'attachment';

        return response()->file(Storage::disk('local')->path($message->attachment_path), [
            'Content-Type' => $message->attachment_mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($message->attachment_name ?: 'attachment').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function conversationPayload(ChatConversation $conversation, int $userId, ChatService $chat): array
    {
        $lastMessage = $conversation->messages()->with('user:id,name')->latest('id')->first();
        $lastReadId = (int) ChatReadState::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->value('last_read_message_id');

        $participantIds = $chat->participantQuery($conversation)->pluck('users.id');
        $online = ChatPresence::query()
            ->whereIn('user_id', $participantIds)
            ->where('last_seen_at', '>=', now()->subSeconds(75))
            ->count();

        $typing = ChatTypingState::query()
            ->with('user:id,name')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $userId)
            ->where('expires_at', '>', now())
            ->get()
            ->map(fn (ChatTypingState $state) => [
                'id' => $state->user_id,
                'name' => $state->user->name,
                'initials' => $this->initials($state->user->name),
            ]);

        return [
            'id' => $conversation->id,
            'scope' => $conversation->scope,
            'name' => $conversation->scope === 'global'
                ? 'Global Chat'
                : ($conversation->property?->name ?? 'Property Chat'),
            'description' => $conversation->scope === 'global' ? 'All hotel staff' : 'Your property team',
            'online_count' => $online,
            'unread_count' => $conversation->messages()
                ->where('id', '>', $lastReadId)
                ->where('user_id', '!=', $userId)
                ->count(),
            'typing' => $typing,
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'body' => $lastMessage->body ?: $lastMessage->attachment_name,
                'sender' => $lastMessage->user->name,
                'created_at' => $lastMessage->created_at->toIso8601String(),
            ] : null,
        ];
    }

    private function messagePayload(ChatMessage $message, int $viewerId, int $readByCount, bool $online): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'is_mine' => (int) $message->user_id === $viewerId,
            'read_by_count' => $readByCount,
            'user' => [
                'id' => $message->user_id,
                'name' => $message->user->name,
                'initials' => $this->initials($message->user->name),
                'online' => $online,
            ],
            'attachment' => $message->attachment_path ? [
                'name' => $message->attachment_name,
                'mime' => $message->attachment_mime,
                'size' => $message->attachment_size,
                'url' => route('chat.attachment', $message),
            ] : null,
        ];
    }

    private function initials(string $name): string
    {
        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
