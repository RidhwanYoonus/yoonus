<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\User;
use App\Models\Wishlist;
use App\Promotions\ReferralService;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', ['seo' => Seo::page(__('shop.auth.login'))]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('auth.failed')]);
        }
        $this->keepCart($request);

        $home = rescue(fn () => $request->user()->affiliate, null, false) ? route('affiliate.dashboard') : route('account');

        return redirect()->intended($home);
    }

    public function showRegister(Request $request)
    {
        return view('auth.register', ['seo' => Seo::page(__('shop.auth.register')), 'ref' => $request->query('ref')]);
    }

    /** The rules for a new password (also checked live in the sign-up form). */
    public static function passwordRule(): Password
    {
        return Password::min(8)->letters()->mixedCase()->numbers()->when(app()->isProduction(), fn ($rule) => $rule->uncompromised());
    }

    /** Checked when the customer leaves the email or password box, so the form is not sent back with an error. */
    public function checkRegister(Request $request)
    {
        $errors = [];
        if ($request->has('email')) {
            $v = validator($request->only('email'), ['email' => 'required|email:rfc|max:191|unique:users,email']);
            if ($v->fails()) {
                $errors['email'] = $v->errors()->first('email');
            }
        }
        if ($request->has('password')) {
            $v = validator($request->only('password'), ['password' => ['required', self::passwordRule()]]);
            if ($v->fails()) {
                $errors['password'] = $v->errors()->first('password');
            }
        }

        return response()->json(['ok' => $errors === [], 'errors' => $errors]);
    }

    public function register(Request $request, ReferralService $referrals)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:191|unique:users,email',
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,}$/'],
            'password' => ['required', 'confirmed', self::passwordRule()],
            'referral_code' => 'nullable|string|max:16',
            'marketing_opt_in' => 'nullable|boolean',
            'is_tourist' => 'nullable|boolean',
        ]);
        // Optional "speed up checkout" step: only checked when the customer filled in an ID number.
        $withId = filled($request->input('id_number'));
        $idData = $withId ? $request->validate(\App\Http\Controllers\AccountController::idRules($request, null)) : [];

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'locale' => app()->getLocale(),
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
        ]);
        $referrals->register($user, $data['referral_code'] ?? null);
        // An affiliate's code (not a customer's): remember the affiliate for their white-page prices.
        if (filled($data['referral_code'] ?? null) && \Illuminate\Support\Facades\Schema::hasColumn('users', 'signup_affiliate_id')
            && ($affiliate = \App\Models\Affiliate::where('code', strtoupper(trim($data['referral_code'])))->where('is_active', true)->first())) {
            $user->forceFill(['signup_affiliate_id' => $affiliate->id, 'affiliate_markup_on' => true])->save();
        }
        // Mobile already confirmed with the code in the sign-up form.
        $verifiedHere = $user->phone && \App\Shop\PhoneVerification::isVerified($request, $user->phone);
        if ($verifiedHere) {
            app(\App\Shop\PhoneVerification::class)->markUser($user, \App\Shop\PhoneVerification::normalise($user->phone));
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_tourist') && $request->has('is_tourist')) {
            $user->forceFill(['is_tourist' => $request->boolean('is_tourist')])->save();
        }
        if ($withId) {
            \App\Http\Controllers\AccountController::saveId($request, $user, $idData);
        }

        $this->signIn($request, $user);

        // Signed up with a mobile number: confirm it right away (checkout needs a verified number).
        if ($user->phone && ! $verifiedHere && \App\Shop\PhoneVerification::enabled()) {
            return redirect()->route('phone.verify')->with('status', __('shop.auth.welcome'));
        }

        return redirect()->intended(route('account'))->with('status', __('shop.auth.welcome'));
    }

    public function signIn(Request $request, User $user): void
    {
        Auth::login($user);
        $this->keepCart($request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Session is regenerated on login; carry the guest cart and wishlist over. */
    private function keepCart(Request $request): void
    {
        $token = $request->session()->get('cart_token');
        $wishlist = $request->session()->get('wishlist', []);
        $phones = $request->session()->get('verified_phones', []);
        $request->session()->regenerate();
        $request->session()->put('verified_phones', $phones);
        // A number verified as a guest a moment ago counts for the account too.
        $user = Auth::user();
        if ($user && ! $user->phone_verified_at && $user->phone && in_array(\App\Shop\PhoneVerification::normalise($user->phone), $phones, true)) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }
        if ($token) {
            $request->session()->put('cart_token', $token);
            Cart::where('token', $token)->update(['user_id' => Auth::id(), 'email' => Auth::user()->email]);
        }
        foreach ($wishlist as $productId) {
            Wishlist::firstOrCreate(['user_id' => Auth::id(), 'product_id' => $productId]);
        }
    }
}
