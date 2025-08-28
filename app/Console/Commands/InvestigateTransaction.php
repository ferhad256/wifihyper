<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\YoPaymentsService;
use Illuminate\Support\Facades\Log;

class InvestigateTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'investigate:transaction {transaction_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Investigate a specific transaction to understand why it\'s failing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $transactionId = $this->argument('transaction_id');
        
        $this->info("🔍 Investigating Transaction: {$transactionId}");
        
        // Find the transaction
        $transaction = Transaction::where('transaction_id', $transactionId)->first();
        
        if (!$transaction) {
            $this->error("❌ Transaction not found in database: {$transactionId}");
            return;
        }
        
        $this->displayTransactionInfo($transaction);
        $this->analyzePaymentDetails($transaction);
        $this->checkYoPaymentsStatus($transaction);
        $this->suggestSolutions($transaction);
        
        // Clean up
        unlink(__FILE__);
    }
    
    /**
     * Display basic transaction information
     */
    protected function displayTransactionInfo($transaction)
    {
        $this->info('📊 Transaction Information:');
        $this->line("  ID: {$transaction->id}");
        $this->line("  Transaction ID: {$transaction->transaction_id}");
        $this->line("  Status: {$transaction->status}");
        $this->line("  Amount: {$transaction->amount} UGX");
        $this->line("  Phone: {$transaction->phone_number}");
        $this->line("  Created: {$transaction->created_at}");
        $this->line("  Updated: {$transaction->updated_at}");
        
        if ($transaction->paid_at) {
            $this->line("  Paid At: {$transaction->paid_at}");
        }
        
        if ($transaction->failed_at) {
            $this->line("  Failed At: {$transaction->failed_at}");
        }
    }
    
    /**
     * Analyze payment details
     */
    protected function analyzePaymentDetails($transaction)
    {
        $this->info('💳 Payment Details Analysis:');
        
        if (!$transaction->payment_details) {
            $this->warn("  ⚠️  No payment_details found");
            return;
        }
        
        $details = $transaction->payment_details;
        
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $this->line("  {$key}: " . json_encode($value));
            } else {
                $this->line("  {$key}: {$value}");
            }
        }
        
        // Check for Yo Payments reference
        if (isset($details['yo_payments_reference'])) {
            $this->info("  ✅ Yo Payments Reference: {$details['yo_payments_reference']}");
        } else {
            $this->warn("  ⚠️  No Yo Payments reference found");
        }
        
        // Check for simulation mode
        if (isset($details['simulation_mode']) && $details['simulation_mode']) {
            $this->info("  🎭 Transaction is in simulation mode");
        }
    }
    
    /**
     * Check Yo Payments status
     */
    protected function checkYoPaymentsStatus($transaction)
    {
        $this->info('🔍 Yo Payments Status Check:');
        
        $yoService = new YoPaymentsService();
        
        try {
            // Try to verify the payment
            $result = $yoService->verifyPayment($transaction);
            
            $this->line("  Verification Result: " . ($result['success'] ? 'Success' : 'Failed'));
            $this->line("  Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['verification_method'])) {
                $this->line("  Method Used: " . $result['verification_method']);
            }
            
            if (isset($result['yo_payments_reference'])) {
                $this->line("  Yo Payments Reference: " . $result['yo_payments_reference']);
            }
            
        } catch (\Exception $e) {
            $this->error("  ❌ Verification failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Suggest solutions based on analysis
     */
    protected function suggestSolutions($transaction)
    {
        $this->info('💡 Suggested Solutions:');
        
        $details = $transaction->payment_details ?? [];
        
        // Check if transaction has Yo Payments reference
        if (!isset($details['yo_payments_reference'])) {
            $this->line("  1. 🔄 Re-initiate payment with Yo Payments");
            $this->line("     - This transaction was never sent to Yo Payments");
            $this->line("     - Use the initiatePayment method to create a new Yo Payments transaction");
        }
        
        // Check if transaction is pending
        if ($transaction->status === 'pending') {
            $this->line("  2. ⏳ Wait for payment completion");
            $this->line("     - Transaction is still pending in your system");
            $this->line("     - User may not have completed the payment yet");
        }
        
        // Check if transaction is simulated
        if (isset($details['simulation_mode']) && $details['simulation_mode']) {
            $this->line("  3. 🎭 Handle simulation mode");
            $this->line("     - This is a simulated transaction for testing");
            $this->line("     - No actual Yo Payments transaction exists");
        }
        
        // Check if transaction is old
        $daysOld = now()->diffInDays($transaction->created_at);
        if ($daysOld > 1) {
            $this->line("  4. ⏰ Transaction may be expired");
            $this->line("     - Transaction is {$daysOld} days old");
            $this->line("     - Yo Payments may have expired this transaction");
        }
        
        $this->line("  5. 📞 Check Yo Payments dashboard");
        $this->line("     - Verify transaction exists in Yo Payments system");
        $this->line("     - Check if transaction is in correct account");
    }
} 