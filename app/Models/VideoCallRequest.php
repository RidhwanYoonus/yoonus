<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VideoCallRequest extends Model
{
    public const STATUSES = ['waiting' => 'Waiting', 'answered' => 'Answered', 'done' => 'Done', 'missed' => 'Missed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            $r->token ??= Str::random(32);
            // Unguessable room name so only the customer and staff can find the call.
            $r->room ??= Str::slug(Settings::get('store_name', 'shop')).'-'.Str::lower(Str::random(16));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** Video room both sides open (Jitsi Meet by default; any Jitsi-compatible server works). */
    public function roomUrl(): string
    {
        return rtrim((string) Settings::get('video_call_base_url', 'https://meet.jit.si'), '/').'/'.$this->room;
    }
}
