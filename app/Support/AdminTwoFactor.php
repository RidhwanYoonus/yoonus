<?php

namespace App\Support;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Two-step sign-in for the admin panel: after the password, a 6-digit code is emailed (and
 * WhatsApped when the staff member has a mobile number). A device can be trusted for 30 days.
 */
class AdminTwoFactor
{
    public const SESSION_KEY = 'admin.two_factor_passed';

    public const COOKIE = 'admin_trusted_device';

    public const TRUST_DAYS = 30;

    private const MAX_TRIES = 5;

    public static function enabled(): bool
    {
        return Settings::bool('admin_two_factor');
    }

    public static function passed(User $user): bool
    {
        if (session(self::SESSION_KEY) === $user->id) {
            return true;
        }
        $cookie = (string) request()->cookie(self::COOKIE);
        if ($cookie !== '' && hash_equals(self::deviceToken($user), $cookie)) {
            session([self::SESSION_KEY => $user->id]);

            return true;
        }

        return false;
    }

    /** Changes when the password changes, so a new password signs out every trusted device. */
    public static function deviceToken(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->getAuthPassword(), (string) config('app.key'));
    }

    public static function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        Cache::put(self::key($user), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(10));
        $messenger = app(Messenger::class);
        $store = (string) Settings::get('store_name');
        $messenger->email($user->email, new ShopMessage("Your {$store} admin sign-in code: {$code}", [
            "Your sign-in code is {$code}. It expires in 10 minutes.",
            'If you did not just try to sign in, change your password now — someone knows it.',
        ]), 'admin_two_factor');
        if ($user->phone) {
            $messenger->whatsapp($user->phone, "{$store} admin sign-in code: {$code} (valid 10 minutes). Never share this code.", 'admin_two_factor');
        }
        SecurityLog::record('two_factor_sent', $user);
    }

    /** null = no code waiting (expired or too many tries). */
    public static function check(User $user, string $code): ?bool
    {
        $entry = Cache::get(self::key($user));
        if (! $entry || $entry['tries'] >= self::MAX_TRIES) {
            Cache::forget(self::key($user));

            return null;
        }
        if (! Hash::check(preg_replace('/\D/', '', $code), $entry['hash'])) {
            $entry['tries']++;
            Cache::put(self::key($user), $entry, now()->addMinutes(10));
            SecurityLog::record('two_factor_failed', $user);

            return false;
        }
        Cache::forget(self::key($user));
        session()->regenerate();
        session([self::SESSION_KEY => $user->id]);
        SecurityLog::record('two_factor_passed', $user);

        return true;
    }

    private static function key(User $user): string
    {
        return 'admin_2fa.'.$user->id;
    }
}
