<?php

namespace App\Http\Middleware;

use App\Support\AdminTwoFactor;
use App\Support\SecurityLog;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Admin panel: signs out after inactivity and asks for the two-step code when it is switched on. */
class AdminSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        $idle = (int) Settings::get('admin_idle_minutes', 30);
        $last = (int) $request->session()->get('admin.last_seen', 0);
        if ($idle > 0 && $last && time() - $last > $idle * 60) {
            SecurityLog::record('idle_logout', $user, details: ['minutes' => $idle]);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->away($request, route('filament.admin.auth.login'));
        }
        $request->session()->put('admin.last_seen', time());

        if (AdminTwoFactor::enabled() && ! AdminTwoFactor::passed($user)) {
            return $this->away($request, route('admin.two-factor'));
        }

        return $next($request);
    }

    /** Livewire background requests can't follow a redirect; normal pages get a plain one. */
    private function away(Request $request, string $url): Response
    {
        if ($request->hasHeader('X-Livewire')) {
            return response('', 419);   // Livewire offers to refresh; the reload lands on $url
        }

        return redirect()->guest($url);
    }
}
