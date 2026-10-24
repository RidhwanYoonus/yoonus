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
        // Uploaded photos, when public/storage is missing (no session, no logging — just the file).
        then: fn () => \Illuminate\Support\Facades\Route::get('storage/{path}', \App\Http\Controllers\PublicFileController::class)
            ->where('path', '.*')->name('storage.file'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [HandleRedirects::class], append: [SetLocale::class, \App\Http\Middleware\TrackAffiliate::class, \App\Http\Middleware\LogActivity::class, SecurityHeaders::class]);
        // Signing out from an old tab must never fail with "Page expired"; a forced sign-out is harmless.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'logout']);
        $middleware->trustProxies(at: '*');   // behind Cloudflare / load balancer
        $middleware->append(\App\Http\Middleware\CloudflareClientIp::class);   // the customer's real address, not Cloudflare's
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "405 Method Not Allowed": a form or a live admin request was sent to a page address that only
        // opens (usually a page left open across an update or a sign-out). Note it, then recover instead of
        // showing the bare error: the live admin offers to reload; a plain form re-opens the page.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::warning('405 method not allowed', [
                'method' => $request->method(), 'url' => $request->fullUrl(), 'base' => $request->getBaseUrl(),
                'livewire' => $request->hasHeader('X-Livewire'), 'from' => $request->headers->get('referer'),
                'user' => $request->user()?->email,
            ]);
            if ($request->hasHeader('X-Livewire')) {
                return response('', 419);   // Livewire: "This page has expired — refresh?"
            }
            if (! $request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->to($request->url(), 303);
            }

            return null;
        });
    })->create();
