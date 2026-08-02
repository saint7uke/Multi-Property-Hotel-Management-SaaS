<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactInquiry extends Model
{
    protected $fillable = [
        'reference_number',
        'property_id',
        'full_name',
        'email',
        'phone',
        'inquiry_type',
        'message',
        'status',
        'assigned_to',
        'resolved_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
