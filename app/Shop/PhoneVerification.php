<?php

namespace App\Shop;

use App\Messaging\Messenger;
use App\Messaging\WhatsApp;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Mobile number check: a 6-digit code goes to the number by WhatsApp; entering it marks the
 * number verified for this visit (and on the account when logged in). Checkout requires it.
 */
class PhoneVerification
{
    public const MAX_SENDS = 3;      // per number per 10 minutes

    public const MAX_TRIES = 5;

    public function __construct(private readonly Messenger $messenger) {}

    public static function enabled(): bool
    {
        return Settings::bool('phone_verification');
    }

    /** Messages can't actually be delivered yet (testing PC): the code is shown on screen instead. */
    public static function testMode(): bool
    {
        return ! WhatsApp::configured();
    }

    public static function normalise(?string $phone): ?string
    {
        return WhatsApp::normalise($phone);
    }

    public static function isVerified(Request $request, ?string $phone): bool
    {
        $phone = self::normalise($phone);
        if (! $phone) {
            return false;
        }
        $user = $request->user();
        if ($user?->phone_verified_at && self::normalise($user->phone) === $phone) {
            return true;
        }

        return in_array($phone, $request->session()->get('verified_phones', []), true);
    }

    /** @return string|null the code itself in test mode, so the flow can be tried before WhatsApp is set up */
    public function send(string $phone, string $locale = 'en'): ?string
    {
        $phone = self::normalise($phone) ?? throw new RuntimeException(__('shop.verify.invalid'));
        $sendsKey = 'phone_otp_sends:'.sha1($phone);
        if (Cache::get($sendsKey, 0) >= self::MAX_SENDS) {
            throw new RuntimeException(__('shop.verify.too_many'));
        }
        Cache::put($sendsKey, Cache::get($sendsKey, 0) + 1, now()->addMinutes(10));

        $code = (string) random_int(100000, 999999);
        Cache::put('phone_otp:'.sha1($phone), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(10));

        Messenger::inLocale($locale, fn () => $this->messenger->whatsapp($phone,
            __('shop.verify.message', ['code' => $code, 'store' => Settings::get('store_name')]), 'phone_verification'));

        return self::testMode() ? $code : null;
    }

    public function check(Request $request, string $phone, string $code): void
    {
        $phone = self::normalise($phone) ?? throw new RuntimeException(__('shop.verify.invalid'));
        $key = 'phone_otp:'.sha1($phone);
        $entry = Cache::get($key);
        if (! $entry) {
            throw new RuntimeException(__('shop.verify.expired'));
        }
        if ($entry['tries'] >= self::MAX_TRIES) {
            Cache::forget($key);
            throw new RuntimeException(__('shop.verify.too_many'));
        }
        if (! Hash::check(preg_replace('/\D/', '', $code), $entry['hash'])) {
            Cache::put($key, ['hash' => $entry['hash'], 'tries' => $entry['tries'] + 1], now()->addMinutes(10));
            throw new RuntimeException(__('shop.verify.wrong'));
        }
        Cache::forget($key);

        $request->session()->put('verified_phones', array_values(array_unique([...$request->session()->get('verified_phones', []), $phone])));
        if ($user = $request->user()) {
            $this->markUser($user, $phone);
        }
    }

    public function markUser(User $user, string $phone): void
    {
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
    }
}
