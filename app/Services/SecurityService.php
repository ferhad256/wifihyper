<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityService
{
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        if (!preg_match('/[@$!%*?&]/', $password)) {
            $errors[] = 'Password must contain at least one special character (@$!%*?&).';
        }
        
        return $errors;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            
            // Remove control characters
            $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
            
            // Trim whitespace
            $data = trim($data);
            
            // HTML encode to prevent XSS
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        $logData = array_merge([
            'event' => $event,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ], $data);
        
        Log::channel('security')->info($event, $logData);
    }
    
    /**
     * Check for suspicious activity
     */
    public static function checkSuspiciousActivity(Request $request, Tenant $tenant = null): bool
    {
        $suspicious = false;
        
        // Check for rapid requests
        $key = 'rapid_requests_' . $request->ip();
        $requests = cache()->get($key, 0);
        
        if ($requests > 100) { // More than 100 requests per minute
            $suspicious = true;
            self::logSecurityEvent('suspicious_rapid_requests', [
                'ip' => $request->ip(),
                'requests' => $requests,
            ]);
        }
        
        cache()->put($key, $requests + 1, 60);
        
        // Check for unusual user agent
        $userAgent = $request->userAgent();
        if (empty($userAgent) || strlen($userAgent) > 500) {
            $suspicious = true;
            self::logSecurityEvent('suspicious_user_agent', [
                'user_agent' => $userAgent,
            ]);
        }
        
        return $suspicious;
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate email format and domain
     */
    public static function validateEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        $domain = substr(strrchr($email, "@"), 1);
        
        // Check if domain has valid DNS records
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if IP is in allowed list
     */
    public static function isIpAllowed(string $ip): bool
    {
        $allowedIps = config('security.allowed_ips', []);
        
        if (empty($allowedIps)) {
            return true; // No restrictions
        }
        
        return in_array($ip, $allowedIps);
    }
    
    /**
     * Rate limit check
     */
    public static function checkRateLimit(string $key, int $maxAttempts, int $decayMinutes = 1): bool
    {
        $attempts = cache()->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        cache()->put($key, $attempts + 1, $decayMinutes * 60);
        return true;
    }
} 