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

    public function register(Request $request, ReferralService $referrals)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:191|unique:users,email',
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->when(app()->isProduction(), fn ($rule) => $rule->uncompromised())],
            'referral_code' => 'nullable|string|max:16',
            'marketing_opt_in' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'locale' => app()->getLocale(),
            'marketing_opt_in' => $request->boolean('marketing_opt_in'),
        ]);
        $referrals->register($user, $data['referral_code'] ?? null);

        $this->signIn($request, $user);

        // Signed up with a mobile number: confirm it right away (checkout needs a verified number).
        if ($user->phone && \App\Shop\PhoneVerification::enabled()) {
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
