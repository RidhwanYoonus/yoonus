<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** EN / AR switch: ?lang= wins, then session, then the signed-in user's preference. */
class SetLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang');
        if (in_array($locale, self::SUPPORTED, true)) {
            $request->session()->put('locale', $locale);
            if ($user = $request->user()) {
                $user->forceFill(['locale' => $locale])->saveQuietly();
            }
        }
        $locale = $request->session()->get('locale', $request->user()?->locale ?? config('app.locale'));
        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'en');

        return $next($request);
    }
}
