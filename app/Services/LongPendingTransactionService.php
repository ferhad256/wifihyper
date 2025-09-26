<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\VoucherTransaction;
use App\Services\JpesaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LongPendingTransactionService
{
    protected $jpesaService;

    public function __construct()
    {
        $this->jpesaService = new JpesaService();
    }

    /**
     * Process long pending transactions efficiently
     */
    public function processLongPendingTransactions(int $timeoutMinutes = 10): array
    {
        $cutoffTime = now()->subMinutes($timeoutMinutes);
        
        Log::info('LongPendingTransactionService: Starting processing', [
            'cutoff_time' => $cutoffTime,
            'timeout_minutes' => $timeoutMinutes
        ]);

        // Get long pending transactions that haven't been processed recently
        $longPendingTransactions = Transaction::where('status', 'pending')
            ->where('created_at', '<', $cutoffTime)
            ->whereDoesntHave('voucherTransaction', function($query) {
                $query->where('sms_sent', true);
            })
            ->with(['voucher', 'package', 'tenant'])
            ->get();

        Log::info('LongPendingTransactionService: Found long pending transactions', [
            'count' => $longPendingTransactions->count(),
            'timeout_minutes' => $timeoutMinutes
        ]);

        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($longPendingTransactions as $transaction) {
            try {
                $result = $this->processLongPendingTransaction($transaction);
                
                $results['processed']++;
                
                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = $result['error'];
                }
                
            } catch (\Exception $e) {
                $results['processed']++;
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
                
                Log::error('LongPendingTransactionService: Exception processing transaction', [
                    'transaction_id' => $transaction->transaction_id,
                    'exception' => $e->getMessage()
                ]);
            }
        }

        Log::info('LongPendingTransactionService: Processing completed', $results);

        return $results;
    }

    /**
     * Process a single long pending transaction
     */
    protected function processLongPendingTransaction(Transaction $transaction): array
    {
        Log::info('LongPendingTransactionService: Processing transaction', [
            'transaction_id' => $transaction->transaction_id,
            'created_at' => $transaction->created_at,
            'age_minutes' => $transaction->created_at->diffInMinutes(now())
        ]);

        // Check if transaction has voucher transaction tracking
        $voucherTransaction = VoucherTransaction::where('transaction_id', $transaction->id)->first();
        
        if (!$voucherTransaction) {
            Log::warning('LongPendingTransactionService: No voucher transaction tracking found', [
                'transaction_id' => $transaction->transaction_id
            ]);
            return ['success' => false, 'error' => 'No voucher transaction tracking'];
        }

        // Skip if SMS already sent
        if ($voucherTransaction->sms_sent) {
            Log::info('LongPendingTransactionService: SMS already sent, skipping', [
                'transaction_id' => $transaction->transaction_id,
                'sms_sent_at' => $voucherTransaction->sms_sent_at
            ]);
            return ['success' => true, 'skipped' => true];
        }

        // Check transaction status with JPesa
        $statusResult = $this->jpesaService->checkAndUpdateTransactionStatus($transaction);
        
        if (!$statusResult['success']) {
            Log::warning('LongPendingTransactionService: Status check failed', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $statusResult['error'] ?? 'Unknown error'
            ]);
            return ['success' => false, 'error' => $statusResult['error'] ?? 'Status check failed'];
        }

        // If transaction is still pending after timeout, mark as failed
        if ($transaction->fresh()->status === 'pending') {
            Log::warning('LongPendingTransactionService: Transaction still pending after timeout, marking as failed', [
                'transaction_id' => $transaction->transaction_id,
                'age_minutes' => $transaction->created_at->diffInMinutes(now())
            ]);

            DB::transaction(function() use ($transaction) {
                $transaction->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);

                // Release the voucher back to unused status
                if ($transaction->voucher) {
                    $transaction->voucher->update([
                        'status' => 'unused',
                        'used_at' => null,
                        'phone_number' => null,
                    ]);
                }
            });

            return ['success' => true, 'action' => 'marked_failed'];
        }

        // If transaction completed, process voucher SMS
        if ($transaction->fresh()->status === 'completed') {
            Log::info('LongPendingTransactionService: Transaction completed, processing voucher SMS', [
                'transaction_id' => $transaction->transaction_id
            ]);

            // Send SMS using EgoSmsService
            $smsService = new \App\Services\EgoSmsService();
            $smsResult = $smsService->sendVoucherCode($transaction->phone_number, $transaction->voucher->code, $transaction->voucher->package);

            if ($smsResult['success']) {
                Log::info('LongPendingTransactionService: Voucher SMS sent successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_code' => $transaction->voucher->code
                ]);
                return ['success' => true, 'action' => 'sms_sent'];
            } else {
                Log::error('LongPendingTransactionService: Voucher SMS failed', [
                    'transaction_id' => $transaction->transaction_id,
                    'error' => $smsResult['message'] ?? 'Unknown error'
                ]);
                return ['success' => false, 'error' => $smsResult['message'] ?? 'SMS failed'];
            }
        }

        return ['success' => true, 'action' => 'no_action_needed'];
    }

    /**
     * Get statistics about long pending transactions
     */
    public function getLongPendingStats(int $timeoutMinutes = 10): array
    {
        $cutoffTime = now()->subMinutes($timeoutMinutes);
        
        $stats = [
            'total_long_pending' => Transaction::where('status', 'pending')
                ->where('created_at', '<', $cutoffTime)
                ->count(),
            'with_sms_sent' => Transaction::where('status', 'pending')
                ->where('created_at', '<', $cutoffTime)
                ->whereHas('voucherTransaction', function($query) {
                    $query->where('sms_sent', true);
                })
                ->count(),
            'without_sms_sent' => Transaction::where('status', 'pending')
                ->where('created_at', '<', $cutoffTime)
                ->whereDoesntHave('voucherTransaction', function($query) {
                    $query->where('sms_sent', true);
                })
                ->count(),
            'timeout_minutes' => $timeoutMinutes,
            'cutoff_time' => $cutoffTime
        ];

        return $stats;
    }
}
