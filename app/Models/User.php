<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'property_id',
        'password',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== 'active' || ! $this->can('dashboard.view')) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasRole('admin'),
            'manager' => $this->hasRole('manager') && filled($this->property_id),
            'receptionist' => $this->hasRole('receptionist') && filled($this->property_id),
            'housekeeping' => $this->hasRole('housekeeping') && filled($this->property_id),
            default => false,
        };
    }

    public function preferredPanelPath(): string
    {
        return match (true) {
            $this->hasRole('admin') => '/admin',
            $this->hasRole('manager') => '/manager',
            $this->hasRole('receptionist') => '/receptionist',
            $this->hasRole('housekeeping') => '/housekeeping',
            default => '/',
        };
    }
}
