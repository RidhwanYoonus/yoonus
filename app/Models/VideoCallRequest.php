<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VideoCallRequest extends Model
{
    public const STATUSES = ['waiting' => 'Waiting', 'answered' => 'Answered', 'done' => 'Done', 'missed' => 'Missed'];

    /** Apps the customer can choose for the call (all work in the UAE). */
    public const PLATFORMS = ['botim' => 'Botim', 'google_meet' => 'Google Meet', 'zoom' => 'Zoom', 'teams' => 'Microsoft Teams'];

    public function platformName(): string
    {
        return self::PLATFORMS[$this->platform] ?? 'online';
    }

    /**
     * Link the customer can open themselves: one staff sent for this call, or the shop's own meeting link
     * when it is for the app the customer chose. Botim calls come from staff, so there is no link.
     */
    public function customerJoinUrl(): ?string
    {
        if (filled($this->meeting_url ?? null)) {
            return $this->joinUrl($this->name);
        }
        $base = (string) Settings::get('video_call_base_url', 'https://meet.jit.si');
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        $matches = match ($this->platform) {
            null, '' => true,   // older requests (before the app choice): as before
            'google_meet' => $host === 'meet.google.com',
            'zoom' => str_ends_with($host, 'zoom.us'),
            'teams' => str_contains($host, 'teams.'),
            default => false,
        };

        return $matches ? $this->joinUrl($this->name) : null;
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        if (filled($this->meeting_url ?? null)) {
            return $this->meeting_url;   // link staff sent for this call
        }
        $base = rtrim((string) Settings::get('video_call_base_url', 'https://meet.jit.si'), '/');

        return self::isFixedMeetingLink($base) ? $base : $base.'/'.$this->room;
    }

    /** Link to open the call: Jitsi gets the person's name and skips its waiting screen; other services get the plain link. */
    public function joinUrl(string $displayName): string
    {
        $url = $this->roomUrl();

        return self::isFixedMeetingLink($url) || filled($this->meeting_url ?? null) && ! str_contains($url, 'jit.si') && ! str_contains($url, '8x8.vc')
            ? $url
            : $url.'#userInfo.displayName='.rawurlencode('"'.$displayName.'"').'&config.prejoinPageEnabled=false';
    }

    /** Services where rooms can't be made by name: the link is one fixed meeting. */
    public static function isFixedMeetingLink(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'meet.google.com' || $host === 'whereby.com' || str_ends_with($host, 'zoom.us')
            || str_ends_with($host, 'teams.microsoft.com') || str_ends_with($host, 'teams.live.com');
    }
}
