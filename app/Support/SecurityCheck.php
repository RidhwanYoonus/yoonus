<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Throwable;

/** Plain-language list of settings that weaken the shop's security, for the dashboard. */
class SecurityCheck
{
    /** @return array<int, array{level: string, text: string}> */
    public static function problems(): array
    {
        $live = app()->isProduction();
        $out = [];
        $add = fn (string $level, string $text) => $out[] = ['level' => $level, 'text' => $text];

        if (config('app.debug') && $live) {
            $add('danger', 'APP_DEBUG is on. Error pages show passwords and keys to visitors. Set APP_DEBUG=false in .env.');
        }
        if (! $live) {
            $add('info', 'APP_ENV is "'.app()->environment().'". When the shop goes online set APP_ENV=production and APP_DEBUG=false in .env.');
        }
        if (! is_dir(public_path('storage')) && ! is_link(public_path('storage'))) {
            $add('danger', 'Product photos cannot be shown: the public/storage link is missing (common after moving to a new server). Press Settings → Speed cache → "Repair photo link".');
        }
        if (blank(config('app.key'))) {
            $add('danger', 'APP_KEY is missing. Run: php artisan key:generate');
        }
        if ($live && ! str_starts_with((string) config('app.url'), 'https://')) {
            $add('danger', 'APP_URL does not start with https://. Use an SSL certificate (e.g. free from Cloudflare) so card details and passwords are encrypted.');
        }
        if (! Settings::bool('admin_two_factor')) {
            $add('warning', 'Two-step sign-in for the admin is off. Turn it on under Settings → Security (send a test email first).');
        }
        if ((int) Settings::get('admin_idle_minutes', 30) === 0) {
            $add('warning', 'Admin auto sign-out is off. Set it to 15–30 minutes under Settings → Security.');
        }
        if ($live && Settings::get('card_gateway') === 'mock') {
            $add('danger', 'Card payments are in test mode (simulated) on the live shop. Choose Mettpay / Stripe / Telr under Settings → Payments.');
        }
        if (str_starts_with((string) Settings::get('stripe_secret_key'), 'sk_test_') && $live) {
            $add('warning', 'Stripe is using a test key on the live shop.');
        }
        try {
            $weak = User::where('is_admin', true)->get()->first(fn (User $u) => Hash::check('password', $u->password) || Hash::check('admin123', $u->password) || str_ends_with($u->email, '@example.com'));
            if ($weak) {
                $add('danger', 'The admin account '.$weak->email.' still uses a demo email or password. Change it in your profile (top right → Profile) now.');
            }
        } catch (Throwable) {
        }
        if (config('mail.default') === 'log' && Settings::bool('admin_two_factor')) {
            $add('warning', 'Email is not set up (mailer = log), so sign-in codes only go to storage/logs/laravel.log. Set up Settings → Email.');
        }

        return $out;
    }
}
