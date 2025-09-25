<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Voucher;
use App\Models\Package;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $primaryService;
    protected $backupService;

    public function __construct()
    {
        $this->primaryService = new UgSmsService();
        $this->backupService = new BackupSmsService();
    }

    /**
     * Send voucher code via SMS with fallback to backup service
     */
    public function sendVoucherCode($phoneNumber, $voucherCode, ?Package $package = null)
    {
        Log::info('SMS Service: Starting voucher SMS send', [
            'phone_number' => $phoneNumber,
            'voucher_code' => $voucherCode,
            'has_backup' => $this->backupService->isEnabled()
        ]);

        // Try primary service first
        $result = $this->primaryService->sendVoucherCode($phoneNumber, $voucherCode, $package);
        
        if ($result['success']) {
            Log::info('SMS Service: Primary service succeeded', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'attempts_made' => $result['attempts_made'] ?? 1
            ]);
            
            return $result;
        }

        Log::warning('SMS Service: Primary service failed, attempting backup', [
            'phone_number' => $phoneNumber,
            'voucher_code' => $voucherCode,
            'primary_error' => $result['message'],
            'primary_attempts' => $result['attempts_made'] ?? 0
        ]);

        // Try backup service if primary failed
        if ($this->backupService->isEnabled()) {
            $backupResult = $this->backupService->sendVoucherCode($phoneNumber, $voucherCode, $package);
            
            if ($backupResult['success']) {
                Log::info('SMS Service: Backup service succeeded', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'service' => 'backup'
                ]);
                
                return $backupResult;
            } else {
                Log::error('SMS Service: Both primary and backup services failed', [
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $voucherCode,
                    'primary_error' => $result['message'],
                    'backup_error' => $backupResult['message']
                ]);
                
                return [
                    'success' => false,
                    'message' => "Both SMS services failed. Primary: {$result['message']}, Backup: {$backupResult['message']}",
                    'primary_attempts' => $result['attempts_made'] ?? 0,
                    'services_tried' => ['primary', 'backup']
                ];
            }
        } else {
            Log::error('SMS Service: Primary service failed and backup not available', [
                'phone_number' => $phoneNumber,
                'voucher_code' => $voucherCode,
                'primary_error' => $result['message'],
                'primary_attempts' => $result['attempts_made'] ?? 0
            ]);
            
            return [
                'success' => false,
                'message' => "Primary SMS service failed: {$result['message']}. Backup service not configured.",
                'primary_attempts' => $result['attempts_made'] ?? 0,
                'services_tried' => ['primary']
            ];
        }
    }

    /**
     * Send notification SMS
     */
    public function sendNotification($phoneNumber, $message)
    {
        Log::info('SMS Service: Starting notification SMS send', [
            'phone_number' => $phoneNumber,
            'has_backup' => $this->backupService->isEnabled()
        ]);

        // Try primary service first
        $result = $this->primaryService->sendNotification($phoneNumber, $message);
        
        if ($result['success']) {
            return $result;
        }

        // Try backup service if primary failed
        if ($this->backupService->isEnabled()) {
            $backupResult = $this->backupService->sendVoucherCode($phoneNumber, '', null);
            
            if ($backupResult['success']) {
                return $backupResult;
            }
        }

        return $result; // Return primary result if backup also fails
    }

    /**
     * Check SMS balance for primary service
     */
    public function checkBalance()
    {
        return $this->primaryService->checkBalance();
    }

    /**
     * Get service status
     */
    public function getServiceStatus()
    {
        return [
            'primary_enabled' => true, // UgSmsService is always enabled
            'backup_enabled' => $this->backupService->isEnabled(),
            'backup_configured' => $this->backupService->isEnabled()
        ];
    }
}
