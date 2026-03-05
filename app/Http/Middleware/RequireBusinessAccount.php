<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBusinessAccount
{
    /**
     * Handle an incoming request.
     *
     * Restricts access to business accounts only.
     * Personal accounts will be redirected with an error message.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->isBusinessAccount()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is only available for business accounts.',
                    'upgrade_url' => '/settings/upgrade',
                ], 403);
            }

            return redirect()
                ->route('paneta.dashboard')
                ->with('error', 'This feature is only available for business accounts. Please upgrade to a business account to access this service.');
        }

        return $next($request);
    }
}
