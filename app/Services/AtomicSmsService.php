<?php

namespace App\Services;

use App\Models\VoucherTransaction;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AtomicSmsService
{
    protected $smsService;

    public function __construct()
    {
        $this->smsService = new SmsService();
    }

    /**
     * Send voucher SMS with atomic deduplication using database locks
     * This ensures only 1 SMS is sent per transaction, even under concurrent conditions
     */
    public function sendVoucherSmsAtomic(Transaction $transaction): array
    {
        // Use database transaction with locking to prevent race conditions
        return DB::transaction(function () use ($transaction) {
            // Lock the voucher transaction record for update
            $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)
                ->lockForUpdate()
                ->first();
            
            if (!$voucherTransaction) {
                Log::error('AtomicSmsService: VoucherTransaction not found', [
                    'transaction_id' => $transaction->transaction_id,
                    'transaction_db_id' => $transaction->id
                ]);
                return ['success' => false, 'message' => 'Voucher transaction tracking not found'];
            }

            // Check if SMS was already sent (double-check after lock)
            if ($voucherTransaction->sms_sent) {
                Log::info('AtomicSmsService: SMS already sent (checked after lock)', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_code' => $transaction->voucher->code ?? 'unknown',
                    'sms_sent_at' => $voucherTransaction->sms_sent_at,
                    'sms_attempts' => $voucherTransaction->sms_attempts
                ]);
                return [
                    'success' => true, 
                    'message' => 'SMS already sent', 
                    'duplicate' => true,
                    'sms_sent_at' => $voucherTransaction->sms_sent_at
                ];
            }

            // Check if voucher is still valid
            $voucher = $transaction->voucher;
            if (!$voucher || !in_array($voucher->status, ['unused', 'used'])) {
                Log::error('AtomicSmsService: Voucher not available for SMS', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_id' => $voucher->id ?? 'not_found',
                    'voucher_status' => $voucher->status ?? 'not_found'
                ]);
                return ['success' => false, 'message' => 'Voucher not available'];
            }

            try {
                // Record SMS attempt (before sending)
                $voucherTransaction->recordSmsAttempt();
                
                Log::info('AtomicSmsService: Attempting SMS send', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_code' => $voucher->code,
                    'phone_number' => $transaction->phone_number,
                    'sms_attempt' => $voucherTransaction->sms_attempts
                ]);

                // Send SMS
                $smsResult = $this->smsService->sendVoucherCode(
                    $transaction->phone_number,
                    $voucher->code,
                    $voucher->package
                );

                if ($smsResult['success']) {
                    // Mark SMS as sent atomically
                    $voucherTransaction->markSmsSent();

                    Log::info('AtomicSmsService: SMS sent successfully', [
                        'transaction_id' => $transaction->transaction_id,
                        'voucher_code' => $voucher->code,
                        'phone_number' => $transaction->phone_number,
                        'package_name' => $voucher->package->name ?? 'Unknown',
                        'sms_attempts' => $voucherTransaction->sms_attempts,
                        'sms_sent_at' => $voucherTransaction->sms_sent_at
                    ]);

                    return [
                        'success' => true,
                        'message' => 'SMS sent successfully',
                        'voucher_code' => $voucher->code,
                        'package_name' => $voucher->package->name ?? 'Unknown',
                        'sms_attempts' => $voucherTransaction->sms_attempts,
                        'sms_sent_at' => $voucherTransaction->sms_sent_at
                    ];
                } else {
                    // Record SMS error
                    $voucherTransaction->recordSmsAttempt($smsResult['message']);

                    Log::error('AtomicSmsService: SMS failed', [
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

                Log::error('AtomicSmsService: SMS exception', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_code' => $voucher->code,
                    'exception' => $e->getMessage(),
                    'sms_attempts' => $voucherTransaction->sms_attempts,
                    'trace' => $e->getTraceAsString()
                ]);

                return [
                    'success' => false,
                    'message' => 'SMS exception: ' . $e->getMessage(),
                    'sms_attempts' => $voucherTransaction->sms_attempts
                ];
            }
        });
    }

    /**
     * Check if SMS was already sent for a transaction
     */
    public function isSmsSent(Transaction $transaction): bool
    {
        $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)->first();
        return $voucherTransaction ? $voucherTransaction->sms_sent : false;
    }

    /**
     * Get SMS status for a transaction
     */
    public function getSmsStatus(Transaction $transaction): array
    {
        $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)->first();
        
        if (!$voucherTransaction) {
            return [
                'sms_sent' => false,
                'sms_sent_at' => null,
                'sms_attempts' => 0,
                'last_error' => null
            ];
        }

        return [
            'sms_sent' => $voucherTransaction->sms_sent,
            'sms_sent_at' => $voucherTransaction->sms_sent_at,
            'sms_attempts' => $voucherTransaction->sms_attempts,
            'last_error' => $voucherTransaction->last_sms_error
        ];
    }
}
