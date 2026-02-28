<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsServiceProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->role !== 'fx_provider') {
            abort(403, 'Access denied. Service Provider role required.');
        }

        return $next($request);
    }
}
