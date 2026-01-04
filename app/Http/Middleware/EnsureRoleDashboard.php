<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRoleDashboard
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('home');
        }

        $routeName = $request->route()?->getName() ?? '';

        $required = strtolower((string) ($request->route('role') ?? ''));
        if ($required === '') {
            return $next($request);
        }

        // fetch user's role name (via relation if loaded). Name is used as URL-friendly identifier
        $userRoleName = strtolower($user->role?->slug ?? $user->role?->name ?? 'guest');

        // Admin can access everything
        if ($userRoleName === 'admin') {
            return $next($request);
        }

        // If this is a daily-report route, ensure non-admins access only their own area's daily-report explicitly
        if (str_ends_with($routeName, 'daily-report')) {
            // Guest must not access report input pages
            if ($required === 'guest') {
                abort(403);
            }
            if ($userRoleName === $required) {
                return $next($request);
            }
            abort(403);
        }

        // Allow guest route only for guest users
        if ($required === 'guest' && $userRoleName === 'guest') {
            return $next($request);
        }

        if ($userRoleName === $required) {
            return $next($request);
        }

        abort(403);
    }
}
