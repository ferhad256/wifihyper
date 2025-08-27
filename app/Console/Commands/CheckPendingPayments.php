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
    protected $signature = 'payments:check-pending {--limit=50 : Number of transactions to check} {--critical=false : Check critical transactions only}';

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
        $critical = $this->option('critical') === 'true';
        
        $this->info("Checking payment status for up to {$limit} pending transactions...");
        $this->info("Critical mode: " . ($critical ? 'ENABLED' : 'DISABLED'));
        
        // Get pending transactions based on critical mode
        if ($critical) {
            // For critical mode, check transactions older than 2 minutes
            $pendingTransactions = Transaction::where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(2))
                ->limit($limit)
                ->get();
        } else {
            // For normal mode, check transactions older than 5 minutes
            $pendingTransactions = Transaction::where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(5))
                ->limit($limit)
                ->get();
        }
        
        if ($pendingTransactions->isEmpty()) {
            $this->info('No pending transactions found to check.');
            return 0;
        }
        
        $this->info("Found {$pendingTransactions->count()} pending transactions to check.");
        
        $yoPayments = new YoPaymentsService();
        $checked = 0;
        $updated = 0;
        $errors = 0;
        $completed = 0;
        $failed = 0;
        
        foreach ($pendingTransactions as $transaction) {
            $this->line("Checking transaction: {$transaction->transaction_id}");
            
            try {
                // Check status using Yo Payments API with comprehensive verification
                $statusResult = $yoPayments->comprehensiveTransactionVerification($transaction);
                
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
                            'automated_check' => true,
                            'check_timestamp' => now(),
                        ]),
                    ];

                    // If payment is completed, add completion timestamp
                    if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                        $updateData['paid_at'] = now();
                        
                        // Update wallet balance and send SMS
                        $this->updateWalletBalance($transaction->transaction_id);
                        $this->sendVoucherSms($transaction->transaction_id);
                        
                        $this->info("  ✅ Payment completed: {$transaction->transaction_id}");
                        $completed++;
                    }

                    // If payment failed, add failure timestamp
                    if ($newStatus === 'failed' && $oldStatus !== 'failed') {
                        $updateData['failed_at'] = now();
                        $this->warn("  ❌ Payment failed: {$transaction->transaction_id}");
                        $failed++;
                    }

                    $transaction->update($updateData);
                    $updated++;
                    
                    if ($oldStatus !== $newStatus) {
                        Log::info('Transaction status updated via automated check', [
                            'transaction_id' => $transaction->transaction_id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'check_type' => $critical ? 'critical' : 'normal',
                        ]);
                    }
                } else {
                    $this->warn("  ⚠️  Status check failed: {$transaction->transaction_id}");
                    $this->warn("  Error: " . ($statusResult['message'] ?? 'Unknown error'));
                    
                    // Log failed status checks for debugging
                    Log::warning('Automated status check failed', [
                        'transaction_id' => $transaction->transaction_id,
                        'error' => $statusResult['message'] ?? 'Unknown error',
                        'check_type' => $critical ? 'critical' : 'normal',
                    ]);
                }
                
                $checked++;
                
                // Add small delay between API calls to avoid rate limiting
                if ($critical) {
                    usleep(100000); // 0.1 second delay for critical checks
                } else {
                    usleep(200000); // 0.2 second delay for normal checks
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error checking transaction {$transaction->transaction_id}: " . $e->getMessage());
                $errors++;
                
                Log::error('Error in automated payment status check', [
                    'transaction_id' => $transaction->transaction_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'check_type' => $critical ? 'critical' : 'normal',
                ]);
            }
        }
        
        // Summary
        $this->newLine();
        $this->info('=== Payment Status Check Summary ===');
        $this->info("Total checked: {$checked}");
        $this->info("Status updated: {$updated}");
        $this->info("Completed: {$completed}");
        $this->info("Failed: {$failed}");
        $this->info("Errors: {$errors}");
        $this->info("Check type: " . ($critical ? 'Critical (30s interval)' : 'Normal (1m interval)'));
        
        // Log summary for monitoring
        Log::info('Automated payment status check completed', [
            'total_checked' => $checked,
            'status_updated' => $updated,
            'completed' => $completed,
            'failed' => $failed,
            'errors' => $errors,
            'check_type' => $critical ? 'critical' : 'normal',
            'timestamp' => now(),
        ]);
        
        return 0;
    }

    /**
     * Update wallet balance for completed transaction
     */
    private function updateWalletBalance($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if (!$transaction) {
                Log::warning('Transaction not found for wallet update', ['transaction_id' => $transactionId]);
                return;
            }

            $tenant = $transaction->tenant;
            if (!$tenant) {
                Log::warning('Tenant not found for wallet update', ['transaction_id' => $transactionId]);
                return;
            }

            // Calculate transaction fee based on plan
            $transactionFee = $this->calculateTransactionFee($transaction->amount, $tenant->subscription_plan);
            
            // Update tenant wallet
            $tenant->wallet_balance += ($transaction->amount - $transactionFee);
            $tenant->save();

            Log::info('Wallet balance updated via automated check', [
                'transaction_id' => $transactionId,
                'tenant_id' => $tenant->id,
                'amount' => $transaction->amount,
                'fee' => $transactionFee,
                'new_balance' => $tenant->wallet_balance,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating wallet balance via automated check', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            if (!$transaction || !$transaction->voucher) {
                Log::warning('Transaction or voucher not found for SMS', ['transaction_id' => $transactionId]);
                return;
            }

            // Send SMS using the existing service
            $smsService = app(\App\Services\SmsService::class);
            $result = $smsService->sendVoucherPurchaseMessage(
                $transaction->phone_number,
                $transaction->voucher->code,
                $transaction->voucher->duration
            );

            if ($result['success']) {
                Log::info('Voucher SMS sent via automated check', [
                    'transaction_id' => $transactionId,
                    'phone_number' => $transaction->phone_number,
                    'voucher_code' => $transaction->voucher->code,
                ]);
            } else {
                Log::warning('Failed to send voucher SMS via automated check', [
                    'transaction_id' => $transactionId,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending voucher SMS via automated check', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Calculate transaction fee based on subscription plan
     */
    private function calculateTransactionFee($amount, $plan)
    {
        if ($plan === 'enterprise') {
            return 0; // No fees for enterprise
        }

        if ($amount <= 1000) {
            return $amount * 0.15; // 15% for amounts <= 1000
        } elseif ($amount <= 5000) {
            return $amount * 0.10; // 10% for amounts 1000-5000
        } else {
            return $amount * 0.05; // 5% for amounts > 5000
        }
    }
} 