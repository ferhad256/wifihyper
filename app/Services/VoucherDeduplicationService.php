<?php

namespace App\Services;

use App\Models\VoucherTransaction;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoucherDeduplicationService
{
    protected $smsService;

    public function __construct()
    {
        $this->smsService = new SmsService();
    }

    /**
     * Create voucher transaction tracking record
     */
    public function createVoucherTransaction(Transaction $transaction): VoucherTransaction
    {
        return VoucherTransaction::create([
            'voucher_id' => $transaction->voucher_id,
            'transaction_id' => $transaction->id,
            'sms_sent' => false,
            'voucher_displayed' => false,
        ]);
    }

    /**
     * Send voucher SMS with deduplication
     */
    public function sendVoucherSms(Transaction $transaction): array
    {
        $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)->first();
        
        if (!$voucherTransaction) {
            Log::error('VoucherTransaction not found for transaction', [
                'transaction_id' => $transaction->transaction_id,
                'transaction_db_id' => $transaction->id
            ]);
            return ['success' => false, 'message' => 'Voucher transaction tracking not found'];
        }

        // Check if SMS was already sent
        if ($voucherTransaction->isSmsSent()) {
            Log::info('Voucher SMS already sent, skipping', [
                'transaction_id' => $transaction->transaction_id,
                'voucher_code' => $transaction->voucher->code,
                'sms_sent_at' => $voucherTransaction->sms_sent_at
            ]);
            return ['success' => true, 'message' => 'SMS already sent', 'duplicate' => true];
        }

        // Check if voucher is still valid
        $voucher = $transaction->voucher;
        if (!$voucher || $voucher->status !== 'unused') {
            Log::error('Voucher not available for SMS', [
                'transaction_id' => $transaction->transaction_id,
                'voucher_id' => $voucher->id ?? 'not_found',
                'voucher_status' => $voucher->status ?? 'not_found'
            ]);
            return ['success' => false, 'message' => 'Voucher not available'];
        }

        try {
            // Record SMS attempt
            $voucherTransaction->recordSmsAttempt();

            // Send SMS
            $smsResult = $this->smsService->sendVoucherCode(
                $transaction->phone_number,
                $voucher->code,
                $voucher->package
            );

            if ($smsResult['success']) {
                // Mark SMS as sent
                $voucherTransaction->markSmsSent();

                // Mark voucher as used
                $voucher->update([
                    'status' => 'used',
                    'used_at' => now(),
                    'phone_number' => $transaction->phone_number,
                ]);

                Log::info('Voucher SMS sent successfully with deduplication', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_code' => $voucher->code,
                    'phone_number' => $transaction->phone_number,
                    'package_name' => $voucher->package->name ?? 'Unknown',
                    'sms_attempts' => $voucherTransaction->sms_attempts
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'voucher_code' => $voucher->code,
                    'package_name' => $voucher->package->name ?? 'Unknown'
                ];
            } else {
                // Record SMS error
                $voucherTransaction->recordSmsAttempt($smsResult['message']);

                Log::error('Voucher SMS failed', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_code' => $voucher->code,
                    'error' => $smsResult['message'],
                    'sms_attempts' => $voucherTransaction->sms_attempts
                ]);

                return [
                    'success' => false,
                    'message' => 'SMS failed: ' . $smsResult['message'],
                    'sms_attempts' => $voucherTransaction->sms_attempts
                ];
            }
        } catch (\Exception $e) {
            $voucherTransaction->recordSmsAttempt($e->getMessage());

            Log::error('Voucher SMS exception', [
                'transaction_id' => $transaction->transaction_id,
                'voucher_code' => $voucher->code,
                'exception' => $e->getMessage(),
                'sms_attempts' => $voucherTransaction->sms_attempts
            ]);

            return [
                'success' => false,
                'message' => 'SMS exception: ' . $e->getMessage(),
                'sms_attempts' => $voucherTransaction->sms_attempts
            ];
        }
    }

    /**
     * Mark voucher as displayed on success page
     */
    public function markVoucherDisplayed(Transaction $transaction): bool
    {
        $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)->first();
        
        if (!$voucherTransaction) {
            Log::error('VoucherTransaction not found for marking displayed', [
                'transaction_id' => $transaction->transaction_id,
                'transaction_db_id' => $transaction->id
            ]);
            return false;
        }

        if ($voucherTransaction->isVoucherDisplayed()) {
            Log::info('Voucher already marked as displayed', [
                'transaction_id' => $transaction->transaction_id,
                'voucher_code' => $transaction->voucher->code,
                'displayed_at' => $voucherTransaction->voucher_displayed_at
            ]);
            return true;
        }

        $voucherTransaction->markVoucherDisplayed();

        Log::info('Voucher marked as displayed', [
            'transaction_id' => $transaction->transaction_id,
            'voucher_code' => $transaction->voucher->code,
            'displayed_at' => $voucherTransaction->voucher_displayed_at
        ]);

        return true;
    }

    /**
     * Get voucher transaction status
     */
    public function getVoucherStatus(Transaction $transaction): array
    {
        $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)->first();
        
        if (!$voucherTransaction) {
            return [
                'sms_sent' => false,
                'voucher_displayed' => false,
                'sms_attempts' => 0,
                'last_error' => null
            ];
        }

        return [
            'sms_sent' => $voucherTransaction->sms_sent,
            'sms_sent_at' => $voucherTransaction->sms_sent_at,
            'voucher_displayed' => $voucherTransaction->voucher_displayed,
            'voucher_displayed_at' => $voucherTransaction->voucher_displayed_at,
            'sms_attempts' => $voucherTransaction->sms_attempts,
            'last_error' => $voucherTransaction->last_sms_error
        ];
    }

    /**
     * Check if transaction has duplicate voucher processing
     */
    public function hasDuplicateProcessing(Transaction $transaction): bool
    {
        $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)->first();
        
        if (!$voucherTransaction) {
            return false;
        }

        return $voucherTransaction->sms_sent || $voucherTransaction->voucher_displayed;
    }
}
