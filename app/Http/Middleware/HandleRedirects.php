<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Admin-managed 301/302 redirects (old URLs from a previous site, renamed products). */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $map = Cache::rememberForever('redirects.map', fn () => Redirect::pluck('to_path', 'from_path')->all());
            $path = '/'.ltrim($request->path(), '/');
            if (isset($map[$path])) {
                $r = Redirect::where('from_path', $path)->first();
                $r?->increment('hits');

                return redirect($map[$path], $r?->status_code ?? 301);
            }
        }

        return $next($request);
    }
}
