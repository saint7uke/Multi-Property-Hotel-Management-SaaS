<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatPresence extends Model
{
    protected $fillable = ['user_id', 'last_seen_at'];

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
