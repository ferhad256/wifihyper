<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security-related configuration settings for the
    | application including allowed IPs, rate limiting, and other security
    | parameters.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Allowed IP Addresses
    |--------------------------------------------------------------------------
    |
    | List of IP addresses that are allowed to access the application.
    | Leave empty to allow all IPs.
    |
    */
    'allowed_ips' => env('SECURITY_ALLOWED_IPS', []),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limiting various endpoints.
    |
    */
    'rate_limiting' => [
        'login_attempts' => env('SECURITY_LOGIN_ATTEMPTS', 5),
        'login_decay_minutes' => env('SECURITY_LOGIN_DECAY_MINUTES', 1),
        'api_requests' => env('SECURITY_API_REQUESTS', 60),
        'api_decay_minutes' => env('SECURITY_API_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Configuration for session security settings.
    |
    */
    'session' => [
        'timeout_minutes' => env('SECURITY_SESSION_TIMEOUT', 30),
        'regenerate_interval' => env('SECURITY_SESSION_REGENERATE_INTERVAL', 300), // 5 minutes
        'secure_cookies' => env('SECURITY_SECURE_COOKIES', true),
        'http_only' => env('SECURITY_HTTP_ONLY_COOKIES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Configuration for password strength requirements.
    |
    */
    'password_policy' => [
        'min_length' => env('SECURITY_PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('SECURITY_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('SECURITY_PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true),
        'require_special_chars' => env('SECURITY_PASSWORD_REQUIRE_SPECIAL_CHARS', true),
        'max_age_days' => env('SECURITY_PASSWORD_MAX_AGE_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Configuration for Content Security Policy headers.
    |
    */
    'csp' => [
        'default_src' => ["'self'"],
        'script_src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
        'style_src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
        'font_src' => ["'self'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
        'img_src' => ["'self'", 'data:', 'https:'],
        'connect_src' => ["'self'"],
        'frame_ancestors' => ["'self'"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configuration for security headers.
    |
    */
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'SAMEORIGIN',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for security logging.
    |
    */
    'logging' => [
        'enabled' => env('SECURITY_LOGGING_ENABLED', true),
        'channel' => env('SECURITY_LOGGING_CHANNEL', 'security'),
        'events' => [
            'login_attempts' => true,
            'failed_logins' => true,
            'suspicious_activity' => true,
            'rate_limit_exceeded' => true,
            'session_timeout' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    |
    | Configuration for secure file uploads.
    |
    */
    'file_uploads' => [
        'allowed_extensions' => ['csv', 'txt'],
        'max_size_kb' => env('SECURITY_MAX_FILE_SIZE_KB', 1024), // 1MB
        'scan_uploads' => env('SECURITY_SCAN_UPLOADS', true),
    ],
]; 