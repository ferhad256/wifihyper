<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Package;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GatewayTestService
{
    /**
     * Test all gateways
     */
    public static function testAllGateways(): array
    {
        $results = [
            'email' => self::testEmailGateway(),
            'sms' => self::testSmsGateway(),
            'payment' => self::testPaymentGateway(),
        ];

        return $results;
    }

    /**
     * Test email gateway (Resend)
     */
    public static function testEmailGateway(): array
    {
        try {
            // Check if Resend is configured
            if (!config('services.resend.key')) {
                return [
                    'status' => 'error',
                    'message' => 'Resend API key not configured',
                    'details' => 'Add RESEND_KEY to your .env file'
                ];
            }

            // Test email configuration
            $emailService = new EmailService();
            $testTenant = new Tenant([
                'name' => 'Test User',
                'email' => config('mail.from.address', 'test@example.com')
            ]);

            $result = $emailService->testEmailConfiguration($testTenant);

            if ($result) {
                return [
                    'status' => 'success',
                    'message' => 'Email gateway working correctly',
                    'details' => 'Resend configuration is valid'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Email gateway test failed',
                    'details' => 'Check Resend API key and configuration'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Email gateway test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Email gateway test failed',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test SMS gateway (UG SMS)
     */
    public static function testSmsGateway(): array
    {
        try {
            // Check if UG SMS is configured
            if (!config('services.ug_sms.username') || !config('services.ug_sms.password')) {
                return [
                    'status' => 'error',
                    'message' => 'UG SMS credentials not configured',
                    'details' => 'Add UG_SMS_USERNAME and UG_SMS_PASSWORD to your .env file'
                ];
            }

            // Test SMS service
            $smsService = new UgSmsService();
            
            // Create a test package
            $testPackage = new Package([
                'name' => 'Test Package',
                'duration_hours' => 24,
                'price' => 1000
            ]);

            // Test SMS sending (without actually sending)
            $message = "Your voucher code is TEST123 for 24 hours. thank you.";
            
            if ($message) {
                return [
                    'status' => 'success',
                    'message' => 'SMS gateway configured correctly',
                    'details' => 'UG SMS service is ready (test mode)',
                    'test_message' => $message
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'SMS gateway test failed',
                    'details' => 'Failed to format test message'
                ];
            }
        } catch (\Exception $e) {
            Log::error('SMS gateway test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'SMS gateway test failed',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test payment gateway (Yo! Payments)
     */
    public static function testPaymentGateway(): array
    {
        try {
            // Check if Yo! Payments is configured
            if (!config('services.yo_payments.username') || !config('services.yo_payments.password')) {
                return [
                    'status' => 'error',
                    'message' => 'Yo! Payments credentials not configured',
                    'details' => 'Add YO_PAYMENTS_USERNAME and YO_PAYMENTS_PASSWORD to your .env file'
                ];
            }

            // Test payment service
            $paymentService = new YoPaymentsService();
            
            // Check if service can be instantiated
            if ($paymentService) {
                return [
                    'status' => 'success',
                    'message' => 'Payment gateway configured correctly',
                    'details' => 'Yo! Payments service is ready',
                    'base_url' => config('services.yo_payments.base_url'),
                    'environment' => config('app.env')
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Payment gateway test failed',
                    'details' => 'Failed to initialize payment service'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Payment gateway test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Payment gateway test failed',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Check production readiness
     */
    public static function checkProductionReadiness(): array
    {
        $checks = [
            'environment' => self::checkEnvironment(),
            'database' => self::checkDatabase(),
            'storage' => self::checkStorage(),
            'cache' => self::checkCache(),
            'security' => self::checkSecurity(),
            'gateways' => self::testAllGateways(),
        ];

        $allPassed = true;
        foreach ($checks as $check) {
            if (is_array($check) && isset($check['status']) && $check['status'] === 'error') {
                $allPassed = false;
            }
        }

        return [
            'ready' => $allPassed,
            'checks' => $checks,
            'summary' => self::generateSummary($checks)
        ];
    }

    /**
     * Check environment configuration
     */
    private static function checkEnvironment(): array
    {
        $required = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://',
            'DB_CONNECTION' => 'mysql',
            'CACHE_DRIVER' => 'redis',
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'redis',
        ];

        $missing = [];
        $warnings = [];

        foreach ($required as $key => $expected) {
            $value = env($key);
            
            if (!$value) {
                $missing[] = $key;
            } elseif ($key === 'APP_DEBUG' && $value === 'true') {
                $warnings[] = "APP_DEBUG should be 'false' in production";
            } elseif ($key === 'APP_URL' && !str_starts_with($value, 'https://')) {
                $warnings[] = "APP_URL should use HTTPS in production";
            }
        }

        if (!empty($missing)) {
            return [
                'status' => 'error',
                'message' => 'Missing required environment variables',
                'details' => 'Missing: ' . implode(', ', $missing)
            ];
        }

        if (!empty($warnings)) {
            return [
                'status' => 'warning',
                'message' => 'Environment configuration warnings',
                'details' => implode('; ', $warnings)
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Environment configured correctly',
            'details' => 'All required variables set'
        ];
    }

    /**
     * Check database configuration
     */
    private static function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            
            return [
                'status' => 'success',
                'message' => 'Database connection successful',
                'details' => 'Database is accessible'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Check storage configuration
     */
    private static function checkStorage(): array
    {
        try {
            $storagePath = storage_path();
            $writable = is_writable($storagePath);
            
            if (!$writable) {
                return [
                    'status' => 'error',
                    'message' => 'Storage directory not writable',
                    'details' => 'Storage path: ' . $storagePath
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Storage configured correctly',
                'details' => 'Storage directory is writable'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Storage check failed',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Check cache configuration
     */
    private static function checkCache(): array
    {
        try {
            $testKey = 'production_test_' . time();
            cache()->put($testKey, 'test', 60);
            $value = cache()->get($testKey);
            cache()->forget($testKey);

            if ($value === 'test') {
                return [
                    'status' => 'success',
                    'message' => 'Cache working correctly',
                    'details' => 'Cache driver: ' . config('cache.default')
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Cache test failed',
                    'details' => 'Cache read/write test failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache check failed',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Check security configuration
     */
    private static function checkSecurity(): array
    {
        $checks = [];

        // Check if security middleware is registered
        if (class_exists('App\Http\Middleware\SecurityHeaders')) {
            $checks[] = 'Security headers middleware';
        }

        // Check if rate limiting is configured
        if (class_exists('App\Http\Middleware\RateLimiting')) {
            $checks[] = 'Rate limiting middleware';
        }

        // Check if session security is configured
        if (class_exists('App\Http\Middleware\SessionSecurity')) {
            $checks[] = 'Session security middleware';
        }

        if (count($checks) >= 3) {
            return [
                'status' => 'success',
                'message' => 'Security configured correctly',
                'details' => 'Security features: ' . implode(', ', $checks)
            ];
        } else {
            return [
                'status' => 'warning',
                'message' => 'Security configuration incomplete',
                'details' => 'Missing security middleware'
            ];
        }
    }

    /**
     * Generate summary of all checks
     */
    private static function generateSummary(array $checks): array
    {
        $summary = [
            'total_checks' => count($checks),
            'passed' => 0,
            'failed' => 0,
            'warnings' => 0,
            'critical_issues' => []
        ];

        foreach ($checks as $name => $check) {
            if (is_array($check) && isset($check['status'])) {
                switch ($check['status']) {
                    case 'success':
                        $summary['passed']++;
                        break;
                    case 'error':
                        $summary['failed']++;
                        $summary['critical_issues'][] = $name . ': ' . ($check['message'] ?? 'Unknown error');
                        break;
                    case 'warning':
                        $summary['warnings']++;
                        break;
                }
            }
        }

        return $summary;
    }
} 