<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Campaign extends Model
{
    use Localized;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'show_countdown' => 'boolean'];
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /** Running now; ended campaigns hide themselves automatically. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function bannerUrl(): ?string
    {
        if (! $this->banner_image) {
            return null;
        }

        return str_starts_with($this->banner_image, 'http') ? $this->banner_image : Storage::disk('public')->url($this->banner_image);
    }
}
