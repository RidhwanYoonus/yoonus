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

    /**
     * Video room both sides open. Jitsi (default) makes a fresh room per call by adding its name;
     * a ready meeting link (Google Meet, Zoom, Teams, Whereby) is used exactly as entered.
     */
    public function roomUrl(): string
    {
        $base = rtrim((string) Settings::get('video_call_base_url', 'https://meet.jit.si'), '/');

        return self::isFixedMeetingLink($base) ? $base : $base.'/'.$this->room;
    }

    /** Services where rooms can't be made by name: the link is one fixed meeting. */
    public static function isFixedMeetingLink(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'meet.google.com' || $host === 'whereby.com' || str_ends_with($host, 'zoom.us')
            || str_ends_with($host, 'teams.microsoft.com') || str_ends_with($host, 'teams.live.com');
    }
}
