<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'guest_id',
        'property_id',
        'room_id',
        'booking_type',
        'event_name',
        'check_in',
        'check_out',
        'adults',
        'children',
        'room_count',
        'preferred_area',
        'wants_breakfast',
        'addons',
        'payment_method',
        'terms_accepted_at',
        'special_request',
        'status',
        'payment_status',
        'estimated_total',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'wants_breakfast' => 'boolean',
            'addons' => 'array',
            'terms_accepted_at' => 'datetime',
            'estimated_total' => 'decimal:2',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeOverlapping(Builder $query, string $checkIn, string $checkOut): Builder
    {
        return $query->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in']);
    }
}
