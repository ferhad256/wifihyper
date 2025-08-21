<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoPaymentsService;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class AutoCheckTransactionBatch extends Command
{
    protected $signature = 'payment:auto-check-batch {--max-attempts=10 : Maximum attempts per transaction} {--delay=15 : Delay between checks in seconds} {--limit=20 : Number of transactions to process}';
    protected $description = 'Automatically check status for multiple pending transactions until successful or max attempts reached';

    public function handle()
    {
        $maxAttempts = $this->option('max-attempts');
        $delaySeconds = $this->option('delay');
        $limit = $this->option('limit');

        $this->info("🚀 Starting batch auto-check for pending transactions");
        $this->info("⏱️  Max attempts: {$maxAttempts}, Delay: {$delaySeconds}s, Limit: {$limit}");

        // Get pending transactions that haven't been checked recently
        $pendingTransactions = Transaction::where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('payment_details->last_status_check')
                      ->orWhere('payment_details->last_status_check', '<', now()->subMinutes(5));
            })
            ->limit($limit)
            ->get();

        if ($pendingTransactions->isEmpty()) {
            $this->info('✅ No pending transactions found for batch checking');
            return 0;
        }

        $this->info("📋 Found {$pendingTransactions->count()} transactions to check");
        $service = new YoPaymentsService();
        $processed = 0;
        $completed = 0;
        $failed = 0;
        $errors = 0;

        foreach ($pendingTransactions as $transaction) {
            $this->info("\n🔍 Processing: {$transaction->transaction_id}");
            
            $attempt = 1;
            $success = false;

            while ($attempt <= $maxAttempts && !$success) {
                $this->line("  📋 Attempt {$attempt}/{$maxAttempts} - " . now()->format('H:i:s'));
                
                try {
                    $result = $service->checkTransactionByReference($transaction->transaction_id, 'PULL');
                    
                    if ($result['success']) {
                        $this->info("  ✅ Transaction found and processed successfully!");
                        
                        // Update local transaction
                        $this->updateLocalTransaction($transaction, $result);
                        
                        if ($result['status'] === 'completed') {
                            $completed++;
                            $this->info("  🎉 Payment completed!");
                        } elseif ($result['status'] === 'failed') {
                            $failed++;
                            $this->warn("  ❌ Payment failed");
                        }
                        
                        $success = true;
                        break;
                    } else {
                        $this->warn("  ⏳ Transaction still processing...");
                        $this->warn("  Message: " . ($result['message'] ?? 'Unknown status'));
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Error checking status: " . $e->getMessage());
                    $errors++;
                }

                if ($attempt < $maxAttempts && !$success) {
                    $this->info("  ⏰ Waiting {$delaySeconds} seconds before next check...");
                    sleep($delaySeconds);
                }
                
                $attempt++;
            }

            if (!$success) {
                $this->warn("  ⚠️  Max attempts reached for {$transaction->transaction_id}");
            }

            $processed++;
            
            // Small delay between transactions to avoid overwhelming the API
            if ($processed < $pendingTransactions->count()) {
                usleep(500000); // 0.5 second delay
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Batch Auto-Check Summary ===');
        $this->info("Total processed: {$processed}");
        $this->info("Completed: {$completed}");
        $this->info("Failed: {$failed}");
        $this->info("Errors: {$errors}");
        $this->info("Max attempts per transaction: {$maxAttempts}");
        $this->info("Delay between checks: {$delaySeconds}s");

        // Log summary
        Log::info('Batch auto-check completed', [
            'total_processed' => $processed,
            'completed' => $completed,
            'failed' => $failed,
            'errors' => $errors,
            'max_attempts' => $maxAttempts,
            'delay_seconds' => $delaySeconds,
            'timestamp' => now(),
        ]);

        return 0;
    }

    private function updateLocalTransaction($transaction, $result)
    {
        try {
            $status = $result['status'] ?? 'pending';
            $transaction->update([
                'status' => $status,
                'payment_details' => array_merge(
                    $transaction->payment_details ?? [],
                    [
                        'last_status_check' => now(),
                        'yo_payments_status' => $result['data']['Response']['TransactionStatus'] ?? 'unknown',
                        'yo_payments_reference' => $result['data']['Response']['TransactionReference'] ?? null,
                        'status_check_result' => $result,
                        'batch_auto_check' => true,
                        'check_timestamp' => now(),
                    ]
                ),
            ]);

            if ($status === 'completed' && $transaction->status !== 'completed') {
                $transaction->update(['paid_at' => now()]);
                
                // Update wallet balance and send SMS
                $this->updateWalletBalance($transaction->transaction_id);
                $this->sendVoucherSms($transaction->transaction_id);
            }

            $this->info("  📝 Local transaction status updated to: {$status}");
            
        } catch (\Exception $e) {
            $this->warn("  ⚠️  Could not update local transaction: " . $e->getMessage());
            Log::error('Error updating local transaction in batch auto-check', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function updateWalletBalance($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if (!$transaction) return;

            $tenant = $transaction->tenant;
            if (!$tenant) return;

            // Calculate transaction fee based on plan
            $transactionFee = $this->calculateTransactionFee($transaction->amount, $tenant->subscription_plan);
            
            // Update tenant wallet
            $tenant->wallet_balance += ($transaction->amount - $transactionFee);
            $tenant->save();

            Log::info('Wallet balance updated via batch auto-check', [
                'transaction_id' => $transactionId,
                'tenant_id' => $tenant->id,
                'amount' => $transaction->amount,
                'fee' => $transactionFee,
                'new_balance' => $tenant->wallet_balance,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating wallet balance via batch auto-check', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendVoucherSms($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if (!$transaction || !$transaction->voucher) return;

            $smsService = app(\App\Services\SmsService::class);
            $result = $smsService->sendVoucherPurchaseMessage(
                $transaction->phone_number,
                $transaction->voucher->code,
                $transaction->voucher->duration
            );

            if ($result['success']) {
                Log::info('Voucher SMS sent via batch auto-check', [
                    'transaction_id' => $transactionId,
                    'phone_number' => $transaction->phone_number,
                    'voucher_code' => $transaction->voucher->code,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending voucher SMS via batch auto-check', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function calculateTransactionFee($amount, $plan)
    {
        if ($plan === 'enterprise') {
            return 0;
        }

        if ($amount <= 1000) {
            return $amount * 0.15;
        } elseif ($amount <= 5000) {
            return $amount * 0.10;
        } else {
            return $amount * 0.05;
        }
    }
} 