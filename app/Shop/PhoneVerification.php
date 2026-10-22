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
    public static function testMode(string $channel = 'whatsapp'): bool
    {
        return match ($channel) {
            'sms' => ! \App\Messaging\Sms::configured(),
            'email' => in_array(config('mail.default'), ['log', 'array'], true),
            default => ! WhatsApp::configured(),
        };
    }

    /** Ways the customer can choose to get the code: WhatsApp always, SMS when set up, email when allowed. */
    public static function channels(): array
    {
        return array_values(array_filter([
            'whatsapp',
            \App\Messaging\Sms::configured() ? 'sms' : null,
            Settings::bool('phone_verify_email', true) ? 'email' : null,
        ]));
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
    /** $channel: 'whatsapp', 'sms' (customers without mobile data) or 'email' (to $email). */
    public function send(string $phone, string $locale = 'en', string $channel = 'whatsapp', ?string $email = null): ?string
    {
        $phone = self::normalise($phone) ?? throw new RuntimeException(__('shop.verify.invalid'));
        $sendsKey = 'phone_otp_sends:'.sha1($phone);
        if (Cache::get($sendsKey, 0) >= self::MAX_SENDS) {
            throw new RuntimeException(__('shop.verify.too_many'));
        }
        Cache::put($sendsKey, Cache::get($sendsKey, 0) + 1, now()->addMinutes(10));

        $code = (string) random_int(100000, 999999);
        Cache::put('phone_otp:'.sha1($phone), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(10));

        $text = fn () => __('shop.verify.message', ['code' => $code, 'store' => Settings::get('store_name')]);
        Messenger::inLocale($locale, fn () => match (true) {
            $channel === 'sms' && \App\Messaging\Sms::configured() => $this->messenger->sms($phone, $text(), 'phone_verification'),
            $channel === 'email' && filled($email) => $this->messenger->email($email, new \App\Mail\ShopMessage(
                __('shop.verify.email_subject', ['code' => $code, 'store' => Settings::get('store_name')]), [$text()], mailLocale: $locale), 'phone_verification'),
            default => $this->messenger->whatsapp($phone, $text(), 'phone_verification'),
        });

        return self::testMode($channel) ? $code : null;
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
