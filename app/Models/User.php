<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'locale', 'buyer_trn', 'marketing_opt_in', 'referred_by_id',
    ];

    protected $hidden = ['password', 'remember_token', 'id_number'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'id_number' => 'encrypted',
            'id_expiry' => 'date',
            'is_tourist' => 'boolean',
            'id_saved_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_staff' => 'boolean',
            'phone_verified_at' => 'datetime',
            'permissions' => 'array',
            'marketing_opt_in' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $u) {
            do {
                $code = Str::upper(Str::random(8));
            } while (static::where('referral_code', $code)->exists());
            $u->referral_code ??= $code;
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin || $this->is_staff;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function loyaltyEntries(): HasMany
    {
        return $this->hasMany(LoyaltyEntry::class)->latest('id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    public function loyaltyBalance(): int
    {
        return (int) $this->loyaltyEntries()->sum('points');
    }

    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->phone;
    }

    /** Saved proof of identity that can be reused at checkout (still valid). */
    public function hasValidSavedId(): bool
    {
        return $this->id_saved_at && $this->id_number && (! $this->id_expiry || $this->id_expiry->isFuture());
    }
}
