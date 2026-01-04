<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
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

        $roleName = strtolower($user->role?->slug ?? $user->role?->name ?? 'guest');
        if ($roleName !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}
