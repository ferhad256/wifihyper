<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimiting
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting for authentication endpoints.
        //
        // POST only: this previously matched GET too, so merely rendering the
        // login form consumed one of the five attempts and a user who looked
        // at the page a few times was locked out before typing anything.
        if ($request->isMethod('post') && $request->is('login', 'register', 'forgot-password', 'reset-password')) {
            $key = $this->resolveRequestSignature($request);

            if (RateLimiter::tooManyAttempts($key, 5)) { // 5 attempts per minute
                $seconds = RateLimiter::availableIn($key);
                return back()->with('error', "Too many attempts. Please try again in {$seconds} seconds.");
            }

            RateLimiter::hit($key, 60); // 1 minute decay
        }

        // Rate limiting for API endpoints
        if ($request->is('api/*')) {
            $key = $this->resolveRequestSignature($request);

            if (RateLimiter::tooManyAttempts($key, 60)) { // 60 requests per minute
                return response()->json(['error' => 'Too many requests'], 429);
            }

            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }

    /**
     * Resolve request signature for rate limiting.
     *
     * Keyed on IP plus the submitted email rather than IP plus user agent.
     * The user agent is attacker-controlled, so rotating it defeated the
     * limiter entirely; the email gives per-account throttling, which is what
     * protects a single account from being brute forced.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $email = (string) $request->input('email', '');

        return sha1($request->ip() . '|' . mb_strtolower(trim($email)));
    }
}
