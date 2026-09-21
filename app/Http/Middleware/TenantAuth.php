<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('tenant')->check()) {
            // guest() records the intended URL, so login can return the user
            // to the page they actually asked for.
            return $request->expectsJson()
                ? response()->json(['error' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('login'));
        }

        $tenant = Auth::guard('tenant')->user();

        // Re-checked on every request, not just at login. Previously this
        // middleware only asked whether a session key existed, so a tenant
        // deactivated or unverified after signing in kept full access until
        // their session happened to expire.
        if (! $tenant->is_active || ! $tenant->hasVerifiedEmail()) {
            Auth::guard('tenant')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['error' => 'Account is not active.'], 403)
                : redirect()->route('login')->with('error', 'Your account is no longer active. Please contact support.');
        }

        return $next($request);
    }
}
