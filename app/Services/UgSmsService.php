<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Voucher;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UgSmsService
{
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $senderId;

    public function __construct()
    {
        $this->baseUrl = config('services.ug_sms.base_url', 'https://ugsms.com/v1/sms/send');
        $this->username = config('services.ug_sms.username');
        $this->password = config('services.ug_sms.password');
        $this->senderId = config('services.ug_sms.sender_id', 'Wifihyper');
    }

    /**
     * Send voucher code via SMS with improved retry logic
     */
    public function sendVoucherCode($phoneNumber, $voucherCode, ?Package $package = null)
    {
        try {
            $message = $this->formatVoucherMessage($voucherCode, $package);
            
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'numbers' => $this->formatPhoneNumber($phoneNumber),
                'message_body' => $message,
            ];

            $timeout = config('services.ug_sms.timeout', 60);
            $retryAttempts = config('services.ug_sms.retry_attempts', 5);
            $retryDelay = config('services.ug_sms.retry_delay', 2000);
            $maxRetryDelay = config('services.ug_sms.max_retry_delay', 10000);
            
            Log::info('UG SMS: Starting voucher SMS send', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'timeout' => $timeout,
                'retry_attempts' => $retryAttempts,
                'retry_delay' => $retryDelay
            ]);

            $response = $this->makeHttpRequestWithRetry($data, $timeout, $retryAttempts, $retryDelay, $maxRetryDelay);

            if (!$response['success']) {
                Log::error('UG SMS API Error', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'error' => $response['message'],
                    'attempts_made' => $response['attempts_made'] ?? 0
                ]);

                return [
                    'success' => false,
                    'message' => $response['message'],
                    'attempts_made' => $response['attempts_made'] ?? 0
                ];
            }

            Log::info('UG SMS API Response', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'response' => $response['data'],
                'attempts_made' => $response['attempts_made'] ?? 1
            ]);

            // Log SMS
            $this->logSms($phoneNumber, $message, $response['data'], $voucherCode);

            return [
                'success' => true,
                'data' => $response['data'],
                'message' => 'SMS sent successfully',
                'attempts_made' => $response['attempts_made'] ?? 1
            ];

        } catch (\Exception $e) {
            Log::error('UG SMS Error', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'SMS service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send bulk SMS with improved retry logic
     */
    public function sendBulkSms($recipients, $message)
    {
        try {
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'numbers' => implode(',', array_map([$this, 'formatPhoneNumber'], $recipients)),
                'message_body' => $message,
            ];

            $timeout = config('services.ug_sms.timeout', 60);
            $retryAttempts = config('services.ug_sms.retry_attempts', 5);
            $retryDelay = config('services.ug_sms.retry_delay', 2000);
            $maxRetryDelay = config('services.ug_sms.max_retry_delay', 10000);
            
            $response = $this->makeHttpRequestWithRetry($data, $timeout, $retryAttempts, $retryDelay, $maxRetryDelay);

            if (!$response['success']) {
                return [
                    'success' => false,
                    'message' => $response['message']
                ];
            }

            return [
                'success' => true,
                'data' => $response['data'],
                'message' => 'Bulk SMS sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error('UG SMS Bulk Error', [
                'recipients' => $recipients,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Bulk SMS service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check SMS balance
     */
    public function checkBalance()
    {
        try {
            $data = [
                'username' => $this->username,
                'password' => $this->password,
            ];

            $timeout = config('services.ug_sms.timeout', 30);
            $retryAttempts = config('services.ug_sms.retry_attempts', 3);
            $retryDelay = config('services.ug_sms.retry_delay', 1000);
            
            $response = Http::timeout($timeout)
                ->retry($retryAttempts, $retryDelay)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://ugsms.com/v1/sms/balance', $data);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to check balance'
                ];
            }

            $responseData = $response->json();

            return [
                'success' => true,
                'data' => $responseData,
                'balance' => $responseData['balance'] ?? 0,
            ];

        } catch (\Exception $e) {
            Log::error('UG SMS Balance Check Error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Balance check error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Format voucher message
     */
    protected function formatVoucherMessage($voucherCode, ?Package $package = null)
    {
        $packageName = "WiFi Access";
        $duration = "24 hours";
        
        if ($package) {
            // Use package name
            $packageName = $package->name;
            
            // Use new flexible duration system if available
            if ($package->duration_value && $package->duration_unit) {
                $duration = $package->formatted_duration;
            } elseif ($package->duration_hours) {
                // Fallback to old duration_hours system
                if ($package->duration_hours < 24) {
                    $duration = $package->duration_hours . " hours";
                } else {
                    $days = $package->duration_hours / 24;
                    $duration = $days . " days";
                }
            }
        }
        
        $message = "Your {$packageName} voucher code is {$voucherCode}.";
        
        return $message;
    }

    /**
     * Format phone number for Uganda (ensure it starts with 0)
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        
        // If it starts with 256, convert to 0 (e.g., 256700427163 -> 0700427163)
        if (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '256') {
            $phoneNumber = '0' . substr($phoneNumber, 3);
        }
        
        // If it's 9 digits and doesn't start with 0, add 0 (e.g., 700427163 -> 0700427163)
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) !== '0') {
            $phoneNumber = '0' . $phoneNumber;
        }
        
        // If it's 10 digits and starts with 0, keep as is (e.g., 0700427163 -> 0700427163)
        if (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = $phoneNumber;
        }
        
        // Validate final format (should be 10 digits starting with 0)
        if (strlen($phoneNumber) !== 10 || substr($phoneNumber, 0, 1) !== '0') {
            Log::warning('Invalid phone number format for SMS', [
                'original' => $phoneNumber,
                'formatted' => $phoneNumber,
            ]);
        }
        
        return $phoneNumber;
    }

    /**
     * Log SMS to database
     */
    protected function logSms($phoneNumber, $message, $response, $voucherCode = null)
    {
        try {
            $tenant = null;
            if ($voucherCode) {
                $voucher = Voucher::where('code', $voucherCode)->first();
                if ($voucher) {
                    $tenant = $voucher->tenant;
                }
            }

            SmsLog::create([
                'tenant_id' => $tenant ? $tenant->id : null,
                'voucher_id' => $voucherCode ? Voucher::where('code', $voucherCode)->first()?->id : null,
                'phone_number' => $phoneNumber,
                'message' => $message,
                'status' => 'sent',
                'gateway_response' => $response,
                'sent_at' => now(),
                'service' => 'primary',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log SMS', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
            ]);
        }
    }

    /**
     * Send notification SMS
     */
    public function sendNotification($phoneNumber, $message)
    {
        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Make HTTP request with exponential backoff retry logic
     */
    protected function makeHttpRequestWithRetry($data, $timeout, $retryAttempts, $retryDelay, $maxRetryDelay)
    {
        $attempts = 0;
        $lastError = null;
        
        while ($attempts < $retryAttempts) {
            $attempts++;
            
            try {
                Log::info('UG SMS: Attempting request', [
                    'attempt' => $attempts,
                    'max_attempts' => $retryAttempts,
                    'timeout' => $timeout
                ]);
                
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'WifiHyper/1.0',
                        'Accept' => 'application/json',
                    ])
                    ->post($this->baseUrl, $data);

                if ($response->successful()) {
                    Log::info('UG SMS: Request successful', [
                        'attempt' => $attempts,
                        'status' => $response->status()
                    ]);
                    
                    return [
                        'success' => true,
                        'data' => $response->json(),
                        'attempts_made' => $attempts
                    ];
                } else {
                    $lastError = "HTTP {$response->status()}: " . $response->body();
                    Log::warning('UG SMS: Request failed', [
                        'attempt' => $attempts,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning('UG SMS: Request exception', [
                    'attempt' => $attempts,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Don't sleep after the last attempt
            if ($attempts < $retryAttempts) {
                $delay = min($retryDelay * pow(2, $attempts - 1), $maxRetryDelay);
                Log::info('UG SMS: Waiting before retry', [
                    'attempt' => $attempts,
                    'delay_ms' => $delay
                ]);
                usleep($delay * 1000); // Convert to microseconds
            }
        }
        
        return [
            'success' => false,
            'message' => "Failed after {$attempts} attempts. Last error: {$lastError}",
            'attempts_made' => $attempts
        ];
    }

    /**
     * Generic SMS sending method
     */
    protected function sendSms($phoneNumber, $message)
    {
        try {
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'numbers' => $this->formatPhoneNumber($phoneNumber),
                'message_body' => $message,
            ];

            $timeout = config('services.ug_sms.timeout', 60);
            $retryAttempts = config('services.ug_sms.retry_attempts', 5);
            $retryDelay = config('services.ug_sms.retry_delay', 2000);
            $maxRetryDelay = config('services.ug_sms.max_retry_delay', 10000);
            
            $response = $this->makeHttpRequestWithRetry($data, $timeout, $retryAttempts, $retryDelay, $maxRetryDelay);

            if (!$response['success']) {
                return [
                    'success' => false,
                    'message' => $response['message']
                ];
            }

            return [
                'success' => true,
                'data' => $response['data'],
                'message' => 'SMS sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error('UG SMS Generic Error', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS service error: ' . $e->getMessage(),
            ];
        }
    }
} 