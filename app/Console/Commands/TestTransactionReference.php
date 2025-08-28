<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\YoPaymentsService;
use Illuminate\Support\Facades\Log;

class TestTransactionReference extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:transaction-reference {--transaction-id=} {--create-test-data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test that transaction references are properly formatted in XML requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Transaction Reference Formatting...');
        
        // Check if we should create test data
        if ($this->option('create-test-data')) {
            $this->createTestData();
        }
        
        // Find a test transaction
        $transactionId = $this->option('transaction-id');
        $transaction = null;
        
        if ($transactionId) {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
        }
        
        if (!$transaction) {
            $transaction = Transaction::where('status', 'pending')->first();
        }
        
        if (!$transaction) {
            $this->warn('⚠️  No test transaction found. Create one with --create-test-data option.');
            return;
        }
        
        $this->info("📊 Testing with transaction: {$transaction->transaction_id}");
        $this->line("  Status: {$transaction->status}");
        $this->line("  Amount: {$transaction->amount} UGX");
        $this->line("  Phone: {$transaction->phone_number}");
        
        // Test the verifyPayment method
        $this->testVerifyPayment($transaction);
        
        // Test the comprehensiveTransactionVerification method
        $this->testComprehensiveVerification($transaction);
        
        $this->info('✅ Transaction reference testing completed!');
        
        // Clean up
        unlink(__FILE__);
    }
    
    /**
     * Test verifyPayment method
     */
    protected function testVerifyPayment($transaction)
    {
        $this->info('🔍 Testing verifyPayment method...');
        
        $yoService = new YoPaymentsService();
        
        // Use reflection to access the protected buildXmlRequest method
        $reflection = new \ReflectionClass($yoService);
        $method = $reflection->getMethod('buildXmlRequest');
        $method->setAccessible(true);
        
        // Mock the parameters that would be built
        $parameters = [
            'TransactionReference' => 'TXN_TEST_REF_' . time(),
            'DepositTransactionType' => 'PULL',
            'PrivateTransactionReference' => $transaction->transaction_id,
        ];
        
        $xmlRequest = $method->invoke($yoService, 'actransactioncheckstatus', $parameters);
        
        $this->line("Generated XML:");
        $this->line($xmlRequest);
        
        // Check if the PrivateTransactionReference contains the transaction ID only
        if (strpos($xmlRequest, $transaction->transaction_id) !== false) {
            $this->info("✅ PrivateTransactionReference correctly contains transaction ID: {$transaction->transaction_id}");
        } else {
            $this->error("❌ PrivateTransactionReference does not contain transaction ID");
        }
        
        // Check if the PrivateTransactionReference contains the full object (should not)
        $fullObjectString = json_encode($transaction);
        if (strpos($xmlRequest, $fullObjectString) !== false) {
            $this->error("❌ PrivateTransactionReference still contains full transaction object");
        } else {
            $this->info("✅ PrivateTransactionReference does not contain full transaction object");
        }
    }
    
    /**
     * Test comprehensiveTransactionVerification method
     */
    protected function testComprehensiveVerification($transaction)
    {
        $this->info('🔍 Testing comprehensiveTransactionVerification method...');
        
        $yoService = new YoPaymentsService();
        
        try {
            $result = $yoService->comprehensiveTransactionVerification($transaction);
            
            $this->info("✅ Comprehensive verification completed");
            $this->line("  Success: " . ($result['success'] ? 'Yes' : 'No'));
            $this->line("  Status: " . ($result['status'] ?? 'Unknown'));
            $this->line("  Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['verification_methods_tried'])) {
                $this->line("  Methods tried: " . implode(', ', $result['verification_methods_tried']));
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Comprehensive verification failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Create test data for testing
     */
    protected function createTestData()
    {
        $this->info('📝 Creating Test Data...');
        
        // Find or create a test tenant
        $tenant = \App\Models\Tenant::first();
        if (!$tenant) {
            $this->warn('⚠️  No tenants found. Please create a tenant first.');
            return;
        }
        
        // Find or create a test package
        $package = \App\Models\Package::first();
        if (!$package) {
            $this->warn('⚠️  No packages found. Please create a package first.');
            return;
        }
        
        // Create a test transaction
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'transaction_id' => 'TXN_TEST_' . time(),
            'amount' => 600,
            'net_amount' => 510,
            'fee_amount' => 90,
            'phone_number' => '256783052764',
            'status' => 'pending',
            'payment_method' => 'mobile_money',
            'payment_details' => [
                'test_transaction' => true,
                'created_at' => now()->toISOString(),
                'provider' => 'MTN',
                'yo_payments_reference' => 'YP_TEST_' . time(),
            ],
        ]);
        
        $this->info("✅ Test transaction created: {$transaction->transaction_id}");
        $this->line("  Amount: {$transaction->amount} UGX");
        $this->line("  Phone: {$transaction->phone_number}");
        $this->line("  Status: {$transaction->status}");
        $this->line("  Yo Payments Reference: {$transaction->payment_details['yo_payments_reference']}");
    }
} 