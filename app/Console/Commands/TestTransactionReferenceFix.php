<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\YoPaymentsService;
use Illuminate\Support\Facades\Log;

class TestTransactionReferenceFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:transaction-reference-fix {--transaction-id=} {--create-test-data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test that the transaction reference fix is working correctly';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Transaction Reference Fix...');
        
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
        $this->line("  Payment Details: " . ($transaction->payment_details ? 'Present' : 'None'));
        
        // Test 1: verifyPayment method
        $this->testVerifyPayment($transaction);
        
        // Test 2: checkTransactionByReference method
        $this->testCheckTransactionByReference($transaction);
        
        // Test 3: comprehensiveTransactionVerification method
        $this->testComprehensiveTransactionVerification($transaction);
        
        // Test 4: XML generation validation
        $this->testXmlGeneration($transaction);
        
        $this->info('✅ Transaction reference fix testing completed!');
        
        // Clean up
        unlink(__FILE__);
    }
    
    /**
     * Test verifyPayment method
     */
    protected function testVerifyPayment($transaction)
    {
        $this->info('🔍 Test 1: Testing verifyPayment method...');
        
        $yoService = new YoPaymentsService();
        
        try {
            $result = $yoService->verifyPayment($transaction);
            
            $this->info("✅ verifyPayment completed");
            $this->line("  Success: " . ($result['success'] ? 'Yes' : 'No'));
            $this->line("  Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['error_type'])) {
                $this->line("  Error Type: " . $result['error_type']);
            }
            
        } catch (\Exception $e) {
            $this->error("❌ verifyPayment failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Test checkTransactionByReference method
     */
    protected function testCheckTransactionByReference($transaction)
    {
        $this->info('🔍 Test 2: Testing checkTransactionByReference method...');
        
        $yoService = new YoPaymentsService();
        
        // Test with string parameters (should work)
        try {
            $result = $yoService->checkTransactionByReference(
                $transaction->transaction_id,
                'PULL',
                $transaction->transaction_id
            );
            
            $this->info("✅ checkTransactionByReference with strings completed");
            $this->line("  Success: " . ($result['success'] ? 'Yes' : 'No'));
            $this->line("  Message: " . ($result['message'] ?? 'No message'));
            
        } catch (\Exception $e) {
            $this->error("❌ checkTransactionByReference with strings failed: {$e->getMessage()}");
        }
        
        // Test with object parameters (should fail gracefully)
        try {
            $result = $yoService->checkTransactionByReference(
                $transaction, // This is an object, should trigger validation error
                'PULL',
                $transaction  // This is also an object
            );
            
            if ($result['success'] === false && isset($result['error_type']) && $result['error_type'] === 'invalid_parameter_type') {
                $this->info("✅ checkTransactionByReference correctly rejected objects");
                $this->line("  Error Type: " . $result['error_type']);
                $this->line("  Message: " . $result['message']);
            } else {
                $this->warn("⚠️  checkTransactionByReference should have rejected objects but didn't");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ checkTransactionByReference with objects failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Test comprehensiveTransactionVerification method
     */
    protected function testComprehensiveTransactionVerification($transaction)
    {
        $this->info('🔍 Test 3: Testing comprehensiveTransactionVerification method...');
        
        $yoService = new YoPaymentsService();
        
        try {
            $result = $yoService->comprehensiveTransactionVerification($transaction);
            
            $this->info("✅ comprehensiveTransactionVerification completed");
            $this->line("  Success: " . ($result['success'] ? 'Yes' : 'No'));
            $this->line("  Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['verification_methods_tried'])) {
                $this->line("  Methods tried: " . implode(', ', $result['verification_methods_tried']));
            }
            
            if (isset($result['all_errors'])) {
                $this->line("  Errors found: " . count($result['all_errors']));
                foreach ($result['all_errors'] as $method => $error) {
                    $this->line("    {$method}: " . $error['message']);
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ comprehensiveTransactionVerification failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Test XML generation validation
     */
    protected function testXmlGeneration($transaction)
    {
        $this->info('🔍 Test 4: Testing XML generation validation...');
        
        $yoService = new YoPaymentsService();
        
        // Use reflection to access the protected buildXmlRequest method
        $reflection = new \ReflectionClass($yoService);
        $method = $reflection->getMethod('buildXmlRequest');
        $method->setAccessible(true);
        
        // Test with proper string parameters
        $parameters = [
            'TransactionReference' => 'YP_TEST_REF_' . time(),
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
        
        // Check XML structure
        if (strpos($xmlRequest, '<TransactionReference>') !== false && 
            strpos($xmlRequest, '<PrivateTransactionReference>') !== false) {
            $this->info("✅ XML structure is correct");
        } else {
            $this->error("❌ XML structure is incorrect");
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