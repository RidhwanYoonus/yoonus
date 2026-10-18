<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Promotions\ReferralService;
use App\Support\SecureHttp;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Continue with Google" (OAuth 2 / OpenID Connect, no extra packages). Client ID and secret come
 * from Google Cloud Console → APIs & Services → Credentials and are entered in Settings → Login.
 */
class GoogleController extends Controller
{
    public static function enabled(): bool
    {
        return filled(Settings::get('google_client_id')) && filled(Settings::get('google_client_secret'));
    }

    public function redirect(Request $request)
    {
        abort_unless(self::enabled(), 404);
        $state = Str::random(40);
        $request->session()->put('google_state', $state);
        if ($request->query('ref')) {
            $request->session()->put('google_ref', $request->query('ref'));
        }

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => Settings::get('google_client_id'),
            'redirect_uri' => route('google.callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request, ReferralService $referrals)
    {
        abort_unless(self::enabled(), 404);
        if (! $request->query('code') || ! hash_equals((string) $request->session()->pull('google_state'), (string) $request->query('state'))) {
            return redirect()->route('login')->withErrors(['email' => __('shop.auth.google_failed')]);
        }

        try {
            // The ID token comes straight from Google's token endpoint over HTTPS, so its claims can be trusted.
            $token = SecureHttp::send(fn ($http) => $http->asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->query('code'),
                'client_id' => Settings::get('google_client_id'),
                'client_secret' => Settings::get('google_client_secret'),
                'redirect_uri' => route('google.callback'),
                'grant_type' => 'authorization_code',
            ])->throw()->json());
            $claims = json_decode(base64_decode(strtr(explode('.', (string) ($token['id_token'] ?? ''))[1] ?? '', '-_', '+/')), true) ?: [];
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => __('shop.auth.google_failed').' ('.Str::limit($e->getMessage(), 160).')']);
        }

        if (($claims['aud'] ?? null) !== Settings::get('google_client_id') || empty($claims['sub']) || empty($claims['email']) || ! ($claims['email_verified'] ?? false)) {
            return redirect()->route('login')->withErrors(['email' => __('shop.auth.google_failed')]);
        }

        $user = User::where('google_id', $claims['sub'])->first()
            ?? User::where('email', strtolower($claims['email']))->first();
        $isNew = ! $user;
        if ($isNew) {
            $user = User::create([
                'name' => $claims['name'] ?? Str::before($claims['email'], '@'),
                'email' => strtolower($claims['email']),
                'password' => Str::random(40),
                'locale' => app()->getLocale(),
            ]);
            $referrals->register($user, $request->session()->pull('google_ref'));
        }
        if ($user->is_admin || $user->is_staff) {
            // Staff log in with their password only.
            return redirect()->route('login')->withErrors(['email' => __('shop.auth.google_staff')]);
        }
        $user->forceFill(['google_id' => $claims['sub'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        app(AuthController::class)->signIn($request, $user);

        return $user->phone_verified_at
            ? redirect()->intended(route('account'))
            : redirect()->route('phone.verify')->with('status', $isNew ? __('shop.auth.welcome') : null);
    }
}
