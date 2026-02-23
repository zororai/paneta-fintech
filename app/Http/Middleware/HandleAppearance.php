<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance') ?? 'system';
        
        // Share with Inertia for Vue components
        Inertia::share('appearance', $appearance);
        
        // Share with Blade views using composer (avoids view not found error)
        View::composer('*', function ($view) use ($appearance) {
            $view->with('appearance', $appearance);
        });

        return $next($request);
    }
}
