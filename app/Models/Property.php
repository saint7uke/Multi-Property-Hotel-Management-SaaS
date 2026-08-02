<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'city',
        'country',
        'tagline',
        'description',
        'hero_image_path',
        'gallery_images',
        'amenities',
        'highlights',
        'contact_email',
        'contact_phone',
        'check_in_time',
        'check_out_time',
        'meta_title',
        'meta_description',
        'offers_breakfast',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'offers_breakfast' => 'boolean',
            'gallery_images' => 'array',
            'amenities' => 'array',
            'highlights' => 'array',
        ];
    }

    public function heroImageUrl(): string
    {
        return $this->hero_image_path
            ? Storage::disk('public')->url($this->hero_image_path)
            : asset('images/home/hotel-hero.jpg');
    }

    /** @return array<int, string> */
    public function galleryImageUrls(): array
    {
        return collect($this->gallery_images)
            ->filter()
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function contactInquiries(): HasMany
    {
        return $this->hasMany(ContactInquiry::class);
    }

    /** @return array<int, string> */
    public function deletionBlockers(): array
    {
        return collect([
            'rooms' => $this->rooms()->exists(),
            'staff accounts' => $this->users()->exists(),
            'reservations' => $this->reservations()->exists(),
            'reviews' => $this->reviews()->exists(),
            'property chat history' => $this->chatConversations()->exists(),
            'guest inquiries' => $this->contactInquiries()->exists(),
        ])->filter()->keys()->values()->all();
    }
}
