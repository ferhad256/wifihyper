<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Voucher;
use App\Models\Package;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EgoSmsService
{
    protected $username;
    protected $password;
    protected $sender;
    protected $baseUrl;
    protected $timeout;
    protected $retryAttempts;
    protected $retryDelay;
    protected $maxRetryDelay;

    public function __construct()
    {
        $this->username = config('services.ego_sms.username');
        $this->password = config('services.ego_sms.password');
        $this->sender = config('services.ego_sms.sender_id', 'WIFIHYPER');
        $this->baseUrl = config('services.ego_sms.base_url', 'https://www.egosms.co/api/v1/plain/');
        $this->timeout = config('services.ego_sms.timeout', 30);
        $this->retryAttempts = config('services.ego_sms.retry_attempts', 3);
        $this->retryDelay = config('services.ego_sms.retry_delay', 1000);
        $this->maxRetryDelay = config('services.ego_sms.max_retry_delay', 5000);
    }

    public function isConfigured(): bool
    {
        return !empty($this->username) && !empty($this->password) && !empty($this->baseUrl);
    }

    public function sendVoucherCode($phoneNumber, $voucherCode, ?Package $package = null)
    {
        if (!$this->isConfigured()) {
            Log::error('EgoSMS: Service not configured', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode
            ]);
            return ['success' => false, 'message' => 'EgoSMS service not configured'];
        }

        $message = $this->formatVoucherMessage($voucherCode, $package);
        $formattedNumber = $this->formatPhoneNumber($phoneNumber);

        Log::info('EgoSMS: Starting voucher SMS send', [
            'phone_number' => $phoneNumber,
            'voucher_code' => $voucherCode,
            'timeout' => $this->timeout,
            'retry_attempts' => $this->retryAttempts
        ]);

        try {
            $response = $this->makeHttpRequestWithRetry($formattedNumber, $message, $this->timeout, $this->retryAttempts, $this->retryDelay, $this->maxRetryDelay);
            
            if ($response['success']) {
                $this->logSms($phoneNumber, $message, $response['data'], $voucherCode);
                Log::info('EgoSMS: Voucher SMS sent successfully', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'response' => $response['data']
                ]);
                return ['success' => true, 'data' => $response['data'], 'message' => 'EgoSMS sent successfully', 'service' => 'ego_sms'];
            } else {
                Log::error('EgoSMS: Failed to send voucher SMS', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'error' => $response['message'],
                    'attempts_made' => $response['attempts_made'] ?? 0
                ]);
                return ['success' => false, 'message' => $response['message'], 'attempts_made' => $response['attempts_made'] ?? 0];
            }
        } catch (\Exception $e) {
            Log::error('EgoSMS: Exception during SMS sending', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => 'EgoSMS service error: ' . $e->getMessage()];
        }
    }

    public function sendBulkSms($phoneNumbers, $message)
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'EgoSMS service not configured'];
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($phoneNumbers as $phoneNumber) {
            $formattedNumber = $this->formatPhoneNumber($phoneNumber);
            $response = $this->makeHttpRequestWithRetry($formattedNumber, $message, $this->timeout, $this->retryAttempts, $this->retryDelay, $this->maxRetryDelay);
            
            if ($response['success']) {
                $successCount++;
                $this->logSms($phoneNumber, $message, $response['data']);
            } else {
                $failureCount++;
            }
            
            $results[] = [
                'phone_number' => $phoneNumber,
                'success' => $response['success'],
                'message' => $response['message']
            ];
        }

        return [
            'success' => $failureCount === 0,
            'data' => $results,
            'summary' => [
                'total' => count($phoneNumbers),
                'success' => $successCount,
                'failed' => $failureCount
            ],
            'message' => "Bulk SMS completed: {$successCount} sent, {$failureCount} failed"
        ];
    }

    protected function makeHttpRequestWithRetry($phoneNumber, $message, $timeout, $retryAttempts, $retryDelay, $maxRetryDelay)
    {
        $lastError = null;
        $attemptsMade = 0;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $attemptsMade = $attempt;
            
            Log::info('EgoSMS: Attempting request', [
                'attempt' => $attempt,
                'max_attempts' => $retryAttempts,
                'timeout' => $timeout
            ]);

            try {
                $url = $this->buildRequestUrl($phoneNumber, $message);
                
                $response = Http::timeout($timeout)
                    ->get($url);

                if ($response->successful()) {
                    $responseBody = trim($response->body());
                    
                    if (strtoupper($responseBody) === 'OK') {
                        Log::info('EgoSMS: Request successful', [
                            'attempt' => $attempt,
                            'response' => $responseBody
                        ]);
                        return [
                            'success' => true,
                            'data' => ['response' => $responseBody, 'url' => $url],
                            'attempts_made' => $attemptsMade
                        ];
                    } else {
                        $errorMessage = "EgoSMS API Error: {$responseBody}";
                        Log::warning('EgoSMS: API returned error', [
                            'attempt' => $attempt,
                            'response' => $responseBody
                        ]);
                        $lastError = $errorMessage;
                    }
                } else {
                    $errorMessage = "HTTP Error: {$response->status()} - {$response->body()}";
                    Log::warning('EgoSMS: HTTP request failed', [
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    $lastError = $errorMessage;
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                Log::warning('EgoSMS: Request exception', [
                    'attempt' => $attempt,
                    'error' => $errorMessage
                ]);
                $lastError = $errorMessage;
            }

            // Don't wait after the last attempt
            if ($attempt < $retryAttempts) {
                $delay = min($retryDelay * pow(2, $attempt - 1), $maxRetryDelay);
                Log::info('EgoSMS: Waiting before retry', [
                    'attempt' => $attempt,
                    'delay_ms' => $delay
                ]);
                usleep($delay * 1000); // Convert to microseconds
            }
        }

        return [
            'success' => false,
            'message' => "Failed after {$attemptsMade} attempts. Last error: {$lastError}",
            'attempts_made' => $attemptsMade
        ];
    }

    protected function buildRequestUrl($phoneNumber, $message)
    {
        $parameters = [
            'number' => $phoneNumber,
            'message' => $message,
            'username' => $this->username,
            'password' => $this->password,
            'sender' => $this->sender,
            'priority' => '0' // Highest priority
        ];

        $queryString = http_build_query($parameters);
        return $this->baseUrl . '?' . $queryString;
    }

    protected function formatVoucherMessage($voucherCode, ?Package $package = null)
    {
        $packageName = "WiFi Access";
        
        if ($package) {
            $packageName = $package->name;
        }

        return "Your {$packageName} voucher is {$voucherCode}";
    }

    protected function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Add + if not present and ensure it starts with country code
        if (!str_starts_with($cleaned, '256')) {
            if (str_starts_with($cleaned, '0')) {
                $cleaned = '256' . substr($cleaned, 1);
            } else {
                $cleaned = '256' . $cleaned;
            }
        }
        
        return '+' . $cleaned;
    }

    protected function logSms($phoneNumber, $message, $response, $voucherCode = null)
    {
        try {
            $status = 'sent';
            if (is_array($response) && isset($response['response']) && strtoupper($response['response']) !== 'OK') {
                $status = 'failed';
            }

            SmsLog::create([
                'tenant_id' => null, // Will be set by the calling service if needed
                'voucher_id' => $voucherCode ? Voucher::where('code', $voucherCode)->first()?->id : null,
                'phone_number' => $phoneNumber,
                'message' => $message,
                'status' => $status,
                'message_id' => null,
                'gateway_response' => $response,
                'sent_at' => $status === 'sent' ? now() : null,
                'service' => 'ego_sms',
            ]);
        } catch (\Exception $e) {
            Log::error('EgoSMS: Failed to log SMS', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage()
            ]);
        }
    }
}
