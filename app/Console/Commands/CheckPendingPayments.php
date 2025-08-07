<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\YoPaymentsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:check-pending {--limit=50 : Number of transactions to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check payment status for pending transactions using Yo Payments API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("Checking payment status for up to {$limit} pending transactions...");
        
        // Get pending transactions that are older than 5 minutes
        $pendingTransactions = Transaction::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(5))
            ->limit($limit)
            ->get();
        
        if ($pendingTransactions->isEmpty()) {
            $this->info('No pending transactions found to check.');
            return 0;
        }
        
        $this->info("Found {$pendingTransactions->count()} pending transactions to check.");
        
        $yoPayments = new YoPaymentsService();
        $checked = 0;
        $updated = 0;
        $errors = 0;
        
        foreach ($pendingTransactions as $transaction) {
            $this->line("Checking transaction: {$transaction->transaction_id}");
            
            try {
                // Check status using Yo Payments API
                $statusResult = $yoPayments->verifyPayment($transaction->transaction_id);
                
                if ($statusResult['success']) {
                    $oldStatus = $transaction->status;
                    $newStatus = $statusResult['status'];
                    
                    // Update transaction with latest status and billing details
                    $updateData = [
                        'status' => $newStatus,
                        'payment_details' => array_merge($transaction->payment_details ?? [], [
                            'last_status_check' => now(),
                            'status_check_result' => $statusResult['data'],
                            'transaction_details' => $statusResult['transaction_details'] ?? [],
                            'yo_payments_status' => $statusResult['data']['Status'] ?? 'unknown',
                            'yo_payments_amount' => $statusResult['data']['Amount'] ?? null,
                            'yo_payments_currency' => $statusResult['data']['Currency'] ?? 'UGX',
                            'yo_payments_receipt' => $statusResult['transaction_details']['receipt_number'] ?? null,
                            'yo_payments_initiation_date' => $statusResult['transaction_details']['initiation_date'] ?? null,
                            'yo_payments_completion_date' => $statusResult['transaction_details']['completion_date'] ?? null,
                        ]),
                    ];

                    // If payment is completed, add completion timestamp
                    if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                        $updateData['paid_at'] = now();
                        
                        // Update wallet balance and send SMS
                        $this->updateWalletBalance($transaction->transaction_id);
                        $this->sendVoucherSms($transaction->transaction_id);
                        
                        $this->info("  ✅ Payment completed: {$transaction->transaction_id}");
                    }

                    // If payment failed, add failure timestamp
                    if ($newStatus === 'failed' && $oldStatus !== 'failed') {
                        $updateData['failed_at'] = now();
                        $this->warn("  ❌ Payment failed: {$transaction->transaction_id}");
                    }

                    $transaction->update($updateData);
                    $updated++;
                    
                    if ($oldStatus !== $newStatus) {
                        $this->line("  📊 Status changed: {$oldStatus} → {$newStatus}");
                    }
                } else {
                    $this->warn("  ⚠️  Status check failed: {$statusResult['message']}");
                    $errors++;
                }
                
                $checked++;
                
                // Add small delay to avoid overwhelming the API
                usleep(500000); // 0.5 seconds
                
            } catch (\Exception $e) {
                $this->error("  💥 Error checking transaction {$transaction->transaction_id}: {$e->getMessage()}");
                $errors++;
                
                Log::error('Payment status check command error', [
                    'transaction_id' => $transaction->transaction_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
        
        $this->info("\n📊 Summary:");
        $this->info("  - Checked: {$checked} transactions");
        $this->info("  - Updated: {$updated} transactions");
        $this->info("  - Errors: {$errors} transactions");
        
        return 0;
    }
    
    /**
     * Update wallet balance for completed transaction
     */
    private function updateWalletBalance($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if ($transaction && $transaction->status === 'pending') {
                \DB::beginTransaction();
                
                // Update transaction status
                $transaction->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);
                
                // Update tenant wallet balance with net amount (after fees)
                $tenant = $transaction->tenant;
                $tenant->wallet_balance += $transaction->net_amount;
                $tenant->save();
                
                \DB::commit();
                
                Log::info('Wallet balance updated via command', [
                    'transaction_id' => $transactionId,
                    'amount' => $transaction->amount,
                    'new_balance' => $tenant->wallet_balance,
                ]);
            }
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Failed to update wallet balance via command', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Send voucher SMS for completed transaction
     */
    private function sendVoucherSms($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if ($transaction && $transaction->status === 'completed' && $transaction->voucher) {
                $voucher = $transaction->voucher;
                
                // Verify voucher is valid and unused
                if ($voucher->status === 'unused' && $voucher->package_id === $transaction->package_id) {
                    $smsService = new \App\Services\UgSmsService();
                    $smsResult = $smsService->sendVoucherCode(
                        $transaction->phone_number,
                        $voucher->code,
                        $transaction->package
                    );

                    if ($smsResult['success']) {
                        // Mark voucher as used
                        $voucher->update([
                            'status' => 'used',
                            'used_at' => now(),
                            'phone_number' => $transaction->phone_number,
                        ]);

                        Log::info('Voucher SMS sent via command', [
                            'transaction_id' => $transactionId,
                            'voucher_code' => $voucher->code,
                            'phone_number' => $transaction->phone_number,
                        ]);
                    } else {
                        Log::error('Failed to send voucher SMS via command', [
                            'transaction_id' => $transactionId,
                            'voucher_code' => $voucher->code,
                            'error' => $smsResult['message'],
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send voucher SMS via command', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
} 