<?php

use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [HandleRedirects::class], append: [SetLocale::class, \App\Http\Middleware\TrackAffiliate::class, \App\Http\Middleware\LogActivity::class, SecurityHeaders::class]);
        // Signing out from an old tab must never fail with "Page expired"; a forced sign-out is harmless.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'logout']);
        $middleware->trustProxies(at: '*');   // behind Cloudflare / load balancer
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
