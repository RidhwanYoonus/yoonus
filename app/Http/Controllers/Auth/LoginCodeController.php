<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Order;
use App\Models\User;
use App\Shop\PhoneVerification;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sign in with a one-time code sent to the mobile (WhatsApp / SMS) or email: no password needed.
 * Guests who ordered before get an account made from their last order, and their earlier orders are added to it.
 */
class LoginCodeController extends Controller
{
    private const MAX_SENDS = 3;

    private const MAX_TRIES = 5;

    public function show(Request $request)
    {
        // Came from the checkout or bag: go back there after signing in.
        $prev = url()->previous();
        if (! $request->session()->has('url.intended') && preg_match('~/(checkout|cart)(\?|$)~', (string) parse_url($prev, PHP_URL_PATH).'?')) {
            $request->session()->put('url.intended', $prev);
        }

        return view('auth.login-code', [
            'seo' => Seo::page(__('shop.code_login.title')),
            'target' => $request->session()->get('login_code_target'),
            'testCode' => $request->session()->get('login_code_test'),
            'channels' => PhoneVerification::channels(),
        ]);
    }

    /** [type, value]: ['email', 'a@b.ae'] or ['phone', '+971501234567'], or null when it is neither. */
    private static function target(string $login): ?array
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return filter_var($login, FILTER_VALIDATE_EMAIL) ? ['email', strtolower($login)] : null;
        }
        $phone = PhoneVerification::normalise($login);

        return $phone ? ['phone', $phone] : null;
    }

    public function send(Request $request)
    {
        $data = $request->validate(['login' => 'required|string|max:191', 'channel' => 'nullable|in:whatsapp,sms']);
        $target = self::target($data['login']);
        if (! $target) {
            return back()->withInput()->withErrors(['login' => __('shop.code_login.invalid')]);
        }
        $key = sha1(implode('|', $target));
        if (Cache::get('login_code_sends:'.$key, 0) >= self::MAX_SENDS) {
            return back()->withInput()->withErrors(['login' => __('shop.verify.too_many')]);
        }
        Cache::put('login_code_sends:'.$key, Cache::get('login_code_sends:'.$key, 0) + 1, now()->addMinutes(10));

        $code = (string) random_int(100000, 999999);
        Cache::put('login_code:'.$key, ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(10));
        $text = __('shop.code_login.message', ['code' => $code, 'store' => Settings::get('store_name')]);
        $messenger = app(Messenger::class);
        $channel = $target[0] === 'email' ? 'email' : (($data['channel'] ?? 'whatsapp') === 'sms' && \App\Messaging\Sms::configured() ? 'sms' : 'whatsapp');
        match ($channel) {
            'email' => $messenger->email($target[1], new ShopMessage(__('shop.code_login.subject', ['code' => $code, 'store' => Settings::get('store_name')]), [$text]), 'login_code'),
            'sms' => $messenger->sms($target[1], $text, 'login_code'),
            default => $messenger->whatsapp($target[1], $text, 'login_code'),
        };
        $request->session()->put('login_code_target', $target);
        // Testing PC (WhatsApp / email not set up yet): show the code on screen, like the mobile check does.
        $request->session()->put('login_code_test', PhoneVerification::testMode($channel) ? $code : null);

        return redirect()->route('login.code')->with('status', __('shop.code_login.sent_'.$channel));
    }

    public function verify(Request $request)
    {
        $target = $request->session()->get('login_code_target');
        if (! $target) {
            return redirect()->route('login.code');
        }
        $code = preg_replace('/\D/', '', (string) $request->validate(['code' => 'required|string|max:12'])['code']);
        $key = 'login_code:'.sha1(implode('|', $target));
        $entry = Cache::get($key);
        if (! $entry || $entry['tries'] >= self::MAX_TRIES) {
            Cache::forget($key);

            return back()->withErrors(['code' => __('shop.verify.expired')]);
        }
        if (! Hash::check($code, $entry['hash'])) {
            Cache::put($key, ['hash' => $entry['hash'], 'tries' => $entry['tries'] + 1], now()->addMinutes(10));

            return back()->withErrors(['code' => __('shop.verify.wrong')]);
        }
        Cache::forget($key);
        $request->session()->forget(['login_code_target', 'login_code_test']);

        [$type, $value] = $target;
        $user = $type === 'email' ? User::where('email', $value)->first() : self::userByPhone($value);
        if ($user && ($user->is_admin || $user->is_staff)) {
            return redirect()->route('login')->withErrors(['email' => __('shop.code_login.staff')]);
        }
        $guestOrders = Order::whereNull('user_id')->where(fn ($q) => $type === 'email' ? $q->where('email', $value) : $q->whereIn('phone', self::phoneForms($value)))->latest('id')->get();
        if (! $user) {
            $last = $guestOrders->first();
            if (! $last) {
                return redirect()->route('register')->withErrors(['email' => __('shop.code_login.none')]);
            }
            if ($type === 'phone' && User::where('email', strtolower($last->email))->exists()) {
                // That email already has an account (with a password): do not take it over from a phone code.
                return redirect()->route('login')->withErrors(['email' => __('shop.code_login.use_email')]);
            }
            $user = User::create([
                'name' => $last->customer_name, 'email' => strtolower($last->email), 'phone' => $last->phone,
                'password' => Str::random(40), 'locale' => app()->getLocale(), 'marketing_opt_in' => false,
            ]);
        }
        if ($type === 'phone') {
            app(PhoneVerification::class)->markUser($user, $value);
        }
        // Earlier guest orders with this verified email / mobile now show under "My orders".
        Order::whereIn('id', $guestOrders->pluck('id'))->update(['user_id' => $user->id]);

        app(AuthController::class)->signIn($request, $user);

        return redirect()->intended(route('account'))->with('status', __('shop.code_login.welcome'));
    }

    /** A customer whose saved mobile is this number (saved in any format). */
    private static function userByPhone(string $phone): ?User
    {
        return User::whereIn('phone', self::phoneForms($phone))->get()
            ->first(fn (User $u) => PhoneVerification::normalise($u->phone) === $phone)
            ?? User::where('phone', 'like', '%'.substr($phone, -9))->get()->first(fn (User $u) => PhoneVerification::normalise($u->phone) === $phone);
    }

    /** Ways the same UAE number is often typed: +971501234567, 971501234567, 0501234567, 050 123 4567. */
    private static function phoneForms(string $phone): array
    {
        $d = ltrim($phone, '+');
        $local = str_starts_with($d, '971') ? '0'.substr($d, 3) : $d;

        return array_values(array_unique([$phone, $d, $local, substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6), '00'.$d]));
    }
}
