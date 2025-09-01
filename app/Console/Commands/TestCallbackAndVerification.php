<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\YoPaymentsService;
use Illuminate\Support\Facades\Log;

class TestCallbackAndVerification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:callback-and-verification {--transaction-id=} {--create-test-data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test callback processing and transaction verification fixes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Callback Processing and Transaction Verification...');
        
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
        
        // Test 1: Callback processing with TransactionReference
        $this->testCallbackProcessing($transaction);
        
        // Test 2: Transaction verification with PULL type
        $this->testTransactionVerification($transaction);
        
        // Test 3: XML generation validation
        $this->testXmlGeneration($transaction);
        
        $this->info('✅ Callback and verification testing completed!');
        
        // Clean up
        unlink(__FILE__);
    }
    
    /**
     * Test callback processing with TransactionReference
     */
    protected function testCallbackProcessing($transaction)
    {
        $this->info('🔍 Test 1: Testing callback processing with TransactionReference...');
        
        $yoService = new YoPaymentsService();
        
        // Test data that matches your cURL test
        $testData = "TransactionReference=YP_TEST_" . time() . "&Status=SUCCESS&Amount=600";
        
        try {
            $result = $yoService->processCallback($testData);
            
            $this->info("✅ Callback processing completed");
            $this->line("  Success: " . ($result['success'] ? 'Yes' : 'No'));
            $this->line("  Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['transaction_id'])) {
                $this->line("  Transaction ID: " . $result['transaction_id']);
            }
            
            if (isset($result['status'])) {
                $this->line("  Status: " . $result['status']);
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Callback processing failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Test transaction verification with PULL type
     */
    protected function testTransactionVerification($transaction)
    {
        $this->info('🔍 Test 2: Testing transaction verification with PULL type...');
        
        $yoService = new YoPaymentsService();
        
        try {
            $result = $yoService->comprehensiveTransactionVerification($transaction);
            
            $this->info("✅ Transaction verification completed");
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
            $this->error("❌ Transaction verification failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Test XML generation validation
     */
    protected function testXmlGeneration($transaction)
    {
        $this->info('🔍 Test 3: Testing XML generation validation...');
        
        $yoService = new YoPaymentsService();
        
        // Use reflection to access the protected buildXmlRequest method
        $reflection = new \ReflectionClass($yoService);
        $method = $reflection->getMethod('buildXmlRequest');
        $method->setAccessible(true);
        
        // Test with PULL type parameters
        $parameters = [
            'TransactionReference' => 'YP_TEST_REF_' . time(),
            'DepositTransactionType' => 'PULL',
            'PrivateTransactionReference' => $transaction->transaction_id,
        ];
        
        $xmlRequest = $method->invoke($yoService, 'actransactioncheckstatus', $parameters);
        
        $this->line("Generated XML:");
        $this->line($xmlRequest);
        
        // Check if DepositTransactionType is PULL
        if (strpos($xmlRequest, '<DepositTransactionType>PULL</DepositTransactionType>') !== false) {
            $this->info("✅ DepositTransactionType correctly set to PULL");
        } else {
            $this->error("❌ DepositTransactionType is not PULL");
        }
        
        // Check if PrivateTransactionReference contains the transaction ID
        if (strpos($xmlRequest, "<PrivateTransactionReference>{$transaction->transaction_id}</PrivateTransactionReference>") !== false) {
            $this->info("✅ PrivateTransactionReference correctly contains transaction ID: {$transaction->transaction_id}");
        } else {
            $this->error("❌ PrivateTransactionReference does not contain transaction ID");
        }
        
        // Check XML structure
        $checks = [
            'XML Declaration' => strpos($xmlRequest, '<?xml version="1.0" encoding="UTF-8"?>') !== false,
            'AutoCreate Tag' => strpos($xmlRequest, '<AutoCreate>') !== false,
            'Request Tag' => strpos($xmlRequest, '<Request>') !== false,
            'APIUsername' => strpos($xmlRequest, '<APIUsername>') !== false,
            'APIPassword' => strpos($xmlRequest, '<APIPassword>') !== false,
            'Method' => strpos($xmlRequest, '<Method>actransactioncheckstatus</Method>') !== false,
            'TransactionReference' => strpos($xmlRequest, '<TransactionReference>') !== false,
            'DepositTransactionType PULL' => strpos($xmlRequest, '<DepositTransactionType>PULL</DepositTransactionType>') !== false,
            'PrivateTransactionReference' => strpos($xmlRequest, '<PrivateTransactionReference>') !== false,
            'Closing Tags' => strpos($xmlRequest, '</Request>') !== false && strpos($xmlRequest, '</AutoCreate>') !== false,
        ];
        
        foreach ($checks as $check => $passed) {
            if ($passed) {
                $this->info("✅ {$check}: OK");
            } else {
                $this->error("❌ {$check}: FAILED");
            }
        }
        
        // Check XML length (should be reasonable)
        $xmlLength = strlen($xmlRequest);
        if ($xmlLength < 1000) {
            $this->info("✅ XML length: {$xmlLength} characters (reasonable)");
        } else {
            $this->warn("⚠️  XML length: {$xmlLength} characters (may be too long)");
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
            'transaction_id' => 'TXN_TEST_CALLBACK_' . time(),
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
                'yo_payments_reference' => 'YP_TEST_CALLBACK_' . time(),
            ],
        ]);
        
        $this->info("✅ Test transaction created: {$transaction->transaction_id}");
        $this->line("  Amount: {$transaction->amount} UGX");
        $this->line("  Phone: {$transaction->phone_number}");
        $this->line("  Status: {$transaction->status}");
        $this->line("  Yo Payments Reference: {$transaction->payment_details['yo_payments_reference']}");
    }
} 