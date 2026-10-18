<?php

namespace App\Support;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Records security events in the database (Security log page) and storage/logs/security.log. */
class SecurityLog
{
    public static function record(string $event, ?User $user = null, ?string $email = null, array $details = []): void
    {
        $request = request();
        $row = [
            'event' => $event,
            'user_id' => $user?->id,
            'email' => $email ?? $user?->email,
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
            'details' => $details ?: null,
        ];
        try {
            Log::channel('security')->info($event, $row);
            SecurityEvent::create($row + ['created_at' => now()]);
        } catch (Throwable) {
            // Before the database update has run: the log file still has it.
        }
    }

    /** True the first time this admin / staff member signs in from this IP address. */
    public static function isNewDevice(User $user): bool
    {
        try {
            return ! SecurityEvent::where('user_id', $user->id)->whereIn('event', ['admin_login', 'two_factor_passed'])
                ->where('ip', request()->ip())->where('created_at', '<', now()->subSeconds(5))->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
