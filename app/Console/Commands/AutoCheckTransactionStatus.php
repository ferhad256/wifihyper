<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoPaymentsService;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class AutoCheckTransactionStatus extends Command
{
    protected $signature = 'payment:auto-check {transaction_id} {--max-attempts=30} {--delay=30}';
    protected $description = 'Automatically check transaction status until successful or max attempts reached';

    public function handle()
    {
        $transactionId = $this->argument('transaction_id');
        $maxAttempts = $this->option('max-attempts');
        $delaySeconds = $this->option('delay');
        
        $this->info("🚀 Starting automatic status check for: {$transactionId}");
        $this->info("⏱️  Max attempts: {$maxAttempts}, Delay between checks: {$delaySeconds}s");
        
        $service = new YoPaymentsService();
        $attempt = 1;
        
        while ($attempt <= $maxAttempts) {
            $this->info("\n📋 Attempt {$attempt}/{$maxAttempts} - " . now()->format('H:i:s'));
            
            try {
                // Use the correct method name: checkTransactionByReference
                $result = $service->checkTransactionByReference($transactionId, 'PULL');
                
                if ($result['success']) {
                    $this->info('✅ Transaction found and processed successfully!');
                    $this->table(['Field', 'Value'], [
                        ['Status', $result['status'] ?? 'N/A'],
                        ['Is Pending', $result['is_pending'] ? 'Yes' : 'No'],
                        ['Message', $result['message'] ?? 'N/A'],
                    ]);
                    
                    // Show transaction details if available
                    if (isset($result['transaction_details']) && !empty($result['transaction_details'])) {
                        $this->info('📊 Transaction Details:');
                        foreach ($result['transaction_details'] as $key => $value) {
                            $this->line("   {$key}: {$value}");
                        }
                    }
                    
                    // Update local transaction status if needed
                    $this->updateLocalTransaction($transactionId, $result);
                    
                    $this->info('🎉 Status check completed successfully!');
                    return 0;
                } else {
                    $this->warn('⏳ Transaction still processing...');
                    $this->warn('Message: ' . ($result['message'] ?? 'Unknown status'));
                }
                
            } catch (\Exception $e) {
                $this->error('❌ Error checking status: ' . $e->getMessage());
            }
            
            if ($attempt < $maxAttempts) {
                $this->info("⏰ Waiting {$delaySeconds} seconds before next check...");
                sleep($delaySeconds);
            }
            
            $attempt++;
        }
        
        $this->error('❌ Max attempts reached. Transaction may still be processing.');
        $this->error('💡 You can run this command again later to continue monitoring.');
        
        return 1;
    }
    
    private function updateLocalTransaction($transactionId, $result)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if ($transaction) {
                $status = $result['status'] ?? 'pending';
                
                $transaction->update([
                    'status' => $status,
                    'payment_details' => array_merge(
                        $transaction->payment_details ?? [],
                        [
                            'last_status_check' => now(),
                            'yo_payments_status' => $result['data']['TransactionStatus'] ?? 'unknown',
                            'yo_payments_reference' => $result['data']['TransactionReference'] ?? null,
                            'status_check_result' => $result,
                        ]
                    ),
                ]);
                
                $this->info("📝 Local transaction status updated to: {$status}");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️  Could not update local transaction: " . $e->getMessage());
        }
    }
} 