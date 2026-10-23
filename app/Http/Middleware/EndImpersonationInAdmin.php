<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Opening the admin while "logged in as" someone returns to the admin's own login (instead of "403 Forbidden"). */
class EndImpersonationInAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has(Impersonation::KEY)) {
            Impersonation::stop();
            if ($request->isMethod('GET') && ! $request->hasHeader('X-Livewire')) {
                return redirect()->to($request->fullUrl());
            }
        }

        return $next($request);
    }
}
