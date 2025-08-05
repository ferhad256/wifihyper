<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SessionSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Regenerate session ID periodically to prevent session fixation
        if (Session::has('tenant_id') && !Session::has('session_regenerated')) {
            Session::regenerate();
            Session::put('session_regenerated', true);
        }
        
        // Check for session timeout (30 minutes)
        if (Session::has('tenant_id') && Session::has('last_activity')) {
            $lastActivity = Session::get('last_activity');
            $timeout = 30 * 60; // 30 minutes in seconds
            
            if (time() - $lastActivity > $timeout) {
                Session::flush();
                return redirect()->route('login')->with('error', 'Session expired. Please login again.');
            }
        }
        
        // Update last activity time
        if (Session::has('tenant_id')) {
            Session::put('last_activity', time());
        }
        
        return $next($request);
    }
} 