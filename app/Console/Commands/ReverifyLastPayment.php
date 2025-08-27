<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoPaymentsService;
use App\Models\Transaction;

class ReverifyLastPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:reverify-last {--transaction-id= : Specific transaction ID to reverify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reverify the last payment using the correct Yo Payments reference';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $transactionId = $this->option('transaction-id');
        
        if (!$transactionId) {
            // Get the most recent transaction
            $transaction = Transaction::latest()->first();
            if (!$transaction) {
                $this->error('No transactions found in the system.');
                return 1;
            }
            $transactionId = $transaction->transaction_id;
            $this->info("Using most recent transaction: {$transactionId}");
        } else {
            // Find the specific transaction
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if (!$transaction) {
                $this->error("Transaction with ID '{$transactionId}' not found.");
                return 1;
            }
        }

        $this->info("Reverifying payment for transaction: {$transaction->transaction_id}");
        $this->newLine();

        // Display transaction details
        $this->info('📋 Transaction Details:');
        $this->line("  ID: {$transaction->transaction_id}");
        $this->line("  Amount: UGX {$transaction->amount}");
        $this->line("  Status: {$transaction->status}");
        $this->line("  Phone: {$transaction->phone_number}");
        $this->line("  Created: {$transaction->created_at}");
        
        // Check if Yo Payments reference exists
        $yoPaymentsReference = $transaction->payment_details['yo_payments_reference'] ?? null;
        if ($yoPaymentsReference) {
            $this->line("  Yo Payments Reference: {$yoPaymentsReference}");
        } else {
            $this->warn("  Yo Payments Reference: NOT FOUND");
        }
        $this->newLine();

        $yoPayments = new YoPaymentsService();

        // Test comprehensive verification
        $this->info('🔍 Testing Comprehensive Verification...');
        
        try {
            $result = $yoPayments->comprehensiveTransactionVerification($transaction);
            
            if ($result['success']) {
                $this->info("✅ Verification SUCCESSFUL!");
                $this->line("  Status: {$result['status']}");
                $this->line("  Message: {$result['message']}");
                
                if (isset($result['verification_method'])) {
                    $this->line("  Method Used: {$result['verification_method']}");
                }
                
                if (isset($result['verification_methods_tried'])) {
                    $this->line("  Methods Tried: " . implode(', ', $result['verification_methods_tried']));
                }
                
                // Show transaction details if available
                if (isset($result['transaction_details']) && !empty($result['transaction_details'])) {
                    $this->newLine();
                    $this->info('📊 Transaction Details from Yo Payments:');
                    foreach ($result['transaction_details'] as $key => $value) {
                        $this->line("  {$key}: {$value}");
                    }
                }
                
            } else {
                $this->error("❌ Verification FAILED!");
                $this->line("  Error: {$result['message']}");
                
                if (isset($result['verification_methods_tried'])) {
                    $this->line("  Methods Tried: " . implode(', ', $result['verification_methods_tried']));
                }
                
                // Check if it's a missing reference error
                if (isset($result['error_type']) && $result['error_type'] === 'missing_reference') {
                    $this->newLine();
                    $this->warn('⚠️  This transaction is missing the Yo Payments reference.');
                    $this->line('   This usually means the payment was never properly initiated with Yo Payments.');
                    $this->line('   You may need to reinitiate the payment.');
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Verification ERROR: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('Reverification completed!');
        
        return 0;
    }
} 