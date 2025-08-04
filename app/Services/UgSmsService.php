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
        $this->baseUrl = 'https://ugsms.com/v1/sms/send';
        $this->username = config('services.ug_sms.username');
        $this->password = config('services.ug_sms.password');
        $this->senderId = config('services.ug_sms.sender_id', 'WiFiSaaS');
    }

    /**
     * Send voucher code via SMS
     */
    public function sendVoucherCode($phoneNumber, $voucherCode, Package $package = null)
    {
        try {
            $message = $this->formatVoucherMessage($voucherCode, $package);
            
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'numbers' => $this->formatPhoneNumber($phoneNumber),
                'message_body' => $message,
            ];

            $options = [
                'http' => [
                    'header' => "Content-Type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($data)
                ]
            ];

            $context = stream_context_create($options);
            $result = file_get_contents($this->baseUrl, false, $context);

            if ($result === FALSE) {
                Log::error('UG SMS API Error', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'error' => 'Failed to connect to SMS API'
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to connect to SMS API'
                ];
            }

            $response = json_decode($result, true);

            Log::info('UG SMS API Response', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'response' => $response,
                'raw_result' => $result
            ]);

            // Log SMS
            $this->logSms($phoneNumber, $message, $response, $voucherCode);

            return [
                'success' => true,
                'data' => $response,
                'message' => 'SMS sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error('UG SMS Error', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send bulk SMS
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

            $options = [
                'http' => [
                    'header' => "Content-Type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($data)
                ]
            ];

            $context = stream_context_create($options);
            $result = file_get_contents($this->baseUrl, false, $context);

            if ($result === FALSE) {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to SMS API'
                ];
            }

            $response = json_decode($result, true);

            return [
                'success' => true,
                'data' => $response,
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

            $options = [
                'http' => [
                    'header' => "Content-Type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($data)
                ]
            ];

            $context = stream_context_create($options);
            $result = file_get_contents('https://ugsms.com/v1/sms/balance', false, $context);

            if ($result === FALSE) {
                return [
                    'success' => false,
                    'message' => 'Failed to check balance'
                ];
            }

            $response = json_decode($result, true);

            return [
                'success' => true,
                'data' => $response,
                'balance' => $response['balance'] ?? 0,
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
    protected function formatVoucherMessage($voucherCode, Package $package = null)
    {
        $message = "🎉 WiFi Voucher Received!\n\n";
        $message .= "🔑 Your WiFi Code: {$voucherCode}\n\n";
        
        if ($package) {
            $message .= "📦 Package: {$package->name}\n";
            if ($package->duration_hours) {
                $message .= "⏰ Duration: {$package->duration_hours} hours\n";
            }
            if ($package->data_limit_mb) {
                $message .= "📊 Data Limit: {$package->data_limit_mb}MB\n";
            }
            $message .= "💰 Amount Paid: UGX " . number_format($package->price) . "\n\n";
        }
        
        $message .= "📱 How to Connect:\n";
        $message .= "1. Go to WiFi settings\n";
        $message .= "2. Select the hotspot\n";
        $message .= "3. Enter the voucher code\n";
        $message .= "4. Enjoy your internet!\n\n";
        
        $message .= "⏰ Valid for 24 hours\n";
        $message .= "📞 Support: Contact hotspot owner\n\n";
        $message .= "Thank you for choosing our WiFi service! 🌐";
        
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

            $options = [
                'http' => [
                    'header' => "Content-Type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($data)
                ]
            ];

            $context = stream_context_create($options);
            $result = file_get_contents($this->baseUrl, false, $context);

            if ($result === FALSE) {
                return [
                    'success' => false,
                    'message' => 'Failed to send SMS'
                ];
            }

            $response = json_decode($result, true);

            return [
                'success' => true,
                'data' => $response,
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