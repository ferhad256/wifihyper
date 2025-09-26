<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Voucher;
use App\Models\Package;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UgSmsService
{
    protected $baseUrl;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->baseUrl = config('services.ug_sms.base_url', 'https://ugsms.com/v1/sms/send');
        $this->username = config('services.ug_sms.username');
        $this->password = config('services.ug_sms.password');
    }

    /**
     * Send voucher code via SMS
     */
    public function sendVoucherCode($phoneNumber, $voucherCode, ?Package $package = null)
    {
        try {
            $message = $this->formatVoucherMessage($voucherCode, $package);
            
            Log::info('UG SMS: Starting voucher SMS send', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'timeout' => config('services.ug_sms.timeout', 30),
                'retry_attempts' => config('services.ug_sms.retry_attempts', 3)
            ]);

            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'numbers' => $this->formatPhoneNumber($phoneNumber),
                'message_body' => $message,
            ];

            $timeout = config('services.ug_sms.timeout', 30);
            $retryAttempts = config('services.ug_sms.retry_attempts', 3);
            $retryDelay = config('services.ug_sms.retry_delay', 1000);

            $response = Http::timeout($timeout)
                ->retry($retryAttempts, $retryDelay)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WifiHyper/1.0',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl, $data);

            if ($response->successful()) {
                $responseData = $response->json();
                
                Log::info('UG SMS: SMS sent successfully', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'response' => $responseData,
                    'status' => $response->status()
                ]);

                // Log SMS
                $this->logSms($phoneNumber, $message, $responseData, $voucherCode);

                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'SMS sent successfully'
                ];
            } else {
                $errorData = $response->json();
                $errorMessage = $errorData['error'] ?? 'Unknown error';
                
                Log::error('UG SMS API Error', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'error' => $errorMessage,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                // Log failed SMS
                $this->logSms($phoneNumber, $message, $errorData, $voucherCode, 'failed');

                return [
                    'success' => false,
                    'message' => 'UG SMS API error: ' . $errorMessage
                ];
            }

        } catch (\Exception $e) {
            Log::error('UG SMS Error', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Log failed SMS
            $this->logSms($phoneNumber, $message ?? '', ['error' => $e->getMessage()], $voucherCode, 'failed');

            return [
                'success' => false,
                'message' => 'UG SMS service error: ' . $e->getMessage()
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
            $packageName = $package->name;
            $duration = $package->duration_text ?? $package->duration . " hours";
        }

        return "Your {$packageName} voucher is {$voucherCode}";
    }

    /**
     * Format phone number for UG SMS
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Remove country code if present (UG SMS expects local format)
        if (str_starts_with($phoneNumber, '256')) {
            $phoneNumber = '0' . substr($phoneNumber, 3);
        }
        
        return $phoneNumber;
    }

    /**
     * Log SMS
     */
    protected function logSms($phoneNumber, $message, $response, $voucherCode = null, $status = 'sent')
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
                'status' => $status,
                'gateway_response' => $response,
                'sent_at' => $status === 'sent' ? now() : null,
                'service' => 'ug_sms',
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
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->username) && !empty($this->password);
    }
}
