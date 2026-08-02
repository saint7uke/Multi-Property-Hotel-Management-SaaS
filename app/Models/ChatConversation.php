<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = ['key', 'scope', 'property_id', 'created_by'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function readStates(): HasMany
    {
        return $this->hasMany(ChatReadState::class, 'conversation_id');
    }

    public function typingStates(): HasMany
    {
        return $this->hasMany(ChatTypingState::class, 'conversation_id');
    }
}
