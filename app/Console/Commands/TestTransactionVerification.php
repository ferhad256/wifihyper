<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoPaymentsService;
use App\Models\Transaction;

class TestTransactionVerification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:test-verification {transaction_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test transaction verification with Yo Payments API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $transactionId = $this->argument('transaction_id');
        
        if (!$transactionId) {
            // Get a recent transaction for testing
            $transaction = Transaction::latest()->first();
            if (!$transaction) {
                $this->error('No transactions found in the system.');
                return 1;
            }
            $transactionId = $transaction->transaction_id;
            $this->info("Using recent transaction: {$transactionId}");
        }

        $this->info("Testing transaction verification for: {$transactionId}");
        $this->newLine();

        $yoPayments = new YoPaymentsService();

        // Test 1: Basic verification
        $this->info('🔍 Testing Method 1: Basic Transaction ID Verification');
        $this->line('Using: verifyPayment()');
        
        try {
            $result1 = $yoPayments->verifyPayment($transactionId);
            $this->line("Result: " . ($result1['success'] ? '✅ Success' : '❌ Failed'));
            $this->line("Status: " . ($result1['status'] ?? 'Unknown'));
            $this->line("Message: " . ($result1['message'] ?? 'No message'));
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
        $this->newLine();

        // Test 2: External reference verification
        $this->info('🔍 Testing Method 2: External Reference Verification');
        $this->line('Using: checkTransactionByReference() with PULL type');
        
        try {
            $result2 = $yoPayments->checkTransactionByReference($transactionId, 'PULL');
            $this->line("Result: " . ($result2['success'] ? '✅ Success' : '❌ Failed'));
            $this->line("Status: " . ($result2['status'] ?? 'Unknown'));
            $this->line("Message: " . ($result2['message'] ?? 'No message'));
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
        $this->newLine();

        // Test 3: PUSH type verification
        $this->info('🔍 Testing Method 3: PUSH Type Verification');
        $this->line('Using: checkTransactionByReference() with PUSH type');
        
        try {
            $result3 = $yoPayments->checkTransactionByReference($transactionId, 'PUSH');
            $this->line("Result: " . ($result3['success'] ? '✅ Success' : '❌ Failed'));
            $this->line("Status: " . ($result3['status'] ?? 'Unknown'));
            $this->line("Message: " . ($result3['message'] ?? 'No message'));
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
        $this->newLine();

        // Test 4: Comprehensive verification
        $this->info('🔍 Testing Method 4: Comprehensive Verification');
        $this->line('Using: comprehensiveTransactionVerification()');
        
        try {
            $result4 = $yoPayments->comprehensiveTransactionVerification($transactionId);
            $this->line("Result: " . ($result4['success'] ? '✅ Success' : '❌ Failed'));
            $this->line("Status: " . ($result4['status'] ?? 'Unknown'));
            $this->line("Message: " . ($result4['message'] ?? 'No message'));
            
            if (isset($result4['verification_method'])) {
                $this->line("Best Method: " . $result4['verification_method']);
            }
            
            if (isset($result4['verification_methods_tried'])) {
                $this->line("Methods Tried: " . implode(', ', $result4['verification_methods_tried']));
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
        $this->newLine();

        $this->info('Transaction verification testing completed!');
        return 0;
    }
} 