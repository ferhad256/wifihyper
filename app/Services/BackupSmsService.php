<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Voucher;
use App\Models\Package;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackupSmsService
{
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $senderId;

    public function __construct()
    {
        $this->baseUrl = config('services.backup_sms.base_url');
        $this->username = config('services.backup_sms.username');
        $this->password = config('services.backup_sms.password');
        $this->senderId = config('services.backup_sms.sender_id', 'WIFIHYPER');
    }

    /**
     * Check if backup SMS service is enabled and configured
     */
    public function isEnabled(): bool
    {
        return config('services.backup_sms.enabled', false) && 
               !empty($this->username) && 
               !empty($this->password) && 
               !empty($this->baseUrl);
    }

    /**
     * Send voucher code via backup SMS service
     */
    public function sendVoucherCode($phoneNumber, $voucherCode, ?Package $package = null)
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Backup SMS service not enabled or configured'
            ];
        }

        try {
            $message = $this->formatVoucherMessage($voucherCode, $package);
            
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'numbers' => $this->formatPhoneNumber($phoneNumber),
                'message_body' => $message,
            ];

            $timeout = 30; // Shorter timeout for backup service
            $retryAttempts = 2; // Fewer retries for backup service
            
            Log::info('Backup SMS: Starting voucher SMS send', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'service' => 'backup'
            ]);

            $response = Http::timeout($timeout)
                ->retry($retryAttempts, 1000)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WifiHyper-Backup/1.0',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl, $data);

            if (!$response->successful()) {
                Log::error('Backup SMS API Error', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'error' => 'Failed to connect to backup SMS API',
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to connect to backup SMS API'
                ];
            }

            $responseData = $response->json();

            Log::info('Backup SMS API Response', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'response' => $responseData,
                'status' => $response->status(),
                'service' => 'backup'
            ]);

            // Log SMS
            $this->logSms($phoneNumber, $message, $responseData, $voucherCode);

            return [
                'success' => true,
                'data' => $responseData,
                'message' => 'Backup SMS sent successfully',
                'service' => 'backup'
            ];

        } catch (\Exception $e) {
            Log::error('Backup SMS Error', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage(),
                'service' => 'backup'
            ]);

            return [
                'success' => false,
                'message' => 'Backup SMS service error: ' . $e->getMessage(),
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

        return "Your WiFi voucher code is: {$voucherCode}\nPackage: {$packageName}\nDuration: {$duration}\n\nConnect to WiFi and enter this code to get internet access.\n\nWifiHyper";
    }

    /**
     * Format phone number
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Add country code if not present
        if (!str_starts_with($phoneNumber, '256')) {
            if (str_starts_with($phoneNumber, '0')) {
                $phoneNumber = '256' . substr($phoneNumber, 1);
            } else {
                $phoneNumber = '256' . $phoneNumber;
            }
        }
        
        return $phoneNumber;
    }

    /**
     * Log SMS
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
                'service' => 'backup'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log backup SMS', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
            ]);
        }
    }
}
