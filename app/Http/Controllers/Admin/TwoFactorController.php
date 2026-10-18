<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminTwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class TwoFactorController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canAccessPanel(filament()->getPanel('admin')), 403);
        if (! AdminTwoFactor::enabled() || AdminTwoFactor::passed($user)) {
            return redirect('/admin');
        }
        if (! $request->session()->has('admin.two_factor_sent')) {
            AdminTwoFactor::send($user);
            $request->session()->put('admin.two_factor_sent', true);
        }

        return view('auth.admin-two-factor', ['seo' => \App\Support\Seo::page('Admin sign-in code'), 'email' => $user->email, 'phone' => $user->phone]);
    }

    public function verify(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canAccessPanel(filament()->getPanel('admin')), 403);
        $request->validate(['code' => 'required|string|max:12']);
        $ok = AdminTwoFactor::check($user, $request->input('code'));
        if ($ok === null) {
            $request->session()->forget('admin.two_factor_sent');

            return back()->withErrors(['code' => 'That code has expired or was entered wrong too many times. A new code has been sent.']);
        }
        if (! $ok) {
            return back()->withErrors(['code' => 'Wrong code. Please check the latest email / WhatsApp.']);
        }
        $request->session()->forget('admin.two_factor_sent');
        if ($request->boolean('trust')) {
            Cookie::queue(Cookie::make(AdminTwoFactor::COOKIE, AdminTwoFactor::deviceToken($user), AdminTwoFactor::TRUST_DAYS * 1440, null, null, $request->isSecure(), true, false, 'lax'));
        }

        return redirect()->intended('/admin');
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canAccessPanel(filament()->getPanel('admin')), 403);
        AdminTwoFactor::send($user);

        return back()->with('status', 'A new code has been sent.');
    }
}
