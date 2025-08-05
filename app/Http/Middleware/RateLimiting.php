<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
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
        $key = $this->resolveRequestSignature($request);
        
        // Rate limiting for authentication endpoints
        if ($request->is('login') || $request->is('register')) {
            if (RateLimiter::tooManyAttempts($key, 5)) { // 5 attempts per minute
                $seconds = RateLimiter::availableIn($key);
                return back()->with('error', "Too many attempts. Please try again in {$seconds} seconds.");
            }
            
            RateLimiter::hit($key, 60); // 1 minute decay
        }
        
        // Rate limiting for API endpoints
        if ($request->is('api/*')) {
            if (RateLimiter::tooManyAttempts($key, 60)) { // 60 requests per minute
                return response()->json(['error' => 'Too many requests'], 429);
            }
            
            RateLimiter::hit($key, 60);
        }
        
        return $next($request);
    }
    
    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        return sha1($ip . '|' . $userAgent);
    }
} 