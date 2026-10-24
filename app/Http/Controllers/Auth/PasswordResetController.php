<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/** "Forgot password?" and the link for accounts made at checkout: a link by email to choose a password. */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password', ['seo' => Seo::page(__('shop.reset.title'))]);
    }

    public function send(Request $request)
    {
        $email = strtolower((string) $request->validate(['email' => 'required|email'])['email']);
        if ($user = User::where('email', $email)->first()) {
            self::sendLink($user, 'password_reset');
        }

        // Same answer whether or not the email has an account.
        return back()->with('status', __('shop.reset.sent'));
    }

    /** Emails the "choose a password" link. $template: password_reset or account_created. */
    public static function sendLink(User $user, string $template): void
    {
        $url = route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);
        $lines = $template === 'account_created' ? [__('shop.reset.created_line')] : [__('shop.reset.line')];
        app(Messenger::class)->email($user->email, new ShopMessage(__('shop.reset.'.($template === 'account_created' ? 'created_subject' : 'subject')), $lines,
            __('shop.reset.button'), $url, $user->locale ?: app()->getLocale()), $template);
    }

    public function form(Request $request, string $token)
    {
        return view('auth.reset-password', ['seo' => Seo::page(__('shop.reset.title')), 'token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required', 'email' => 'required|email',
            'password' => ['required', 'confirmed', AuthController::passwordRule()],
        ]);
        $status = Password::broker()->reset($data, function (User $user, string $password) use ($request) {
            $user->forceFill(['password' => $password])->save();
            app(AuthController::class)->signIn($request, $user);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('account')->with('status', __('shop.reset.done'))
            : back()->withErrors(['email' => __('shop.reset.invalid')]);
    }
}
