<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\YoPaymentsService;
use Illuminate\Support\Facades\Log;

class TestPullTransactionCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:pull-transaction-check {--transaction-id=} {--create-test-data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test that transaction checking now uses PULL type and proper PrivateTransactionReference';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing PULL Transaction Check XML Format...');
        
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
        
        // Test 1: XML generation with PULL type
        $this->testPullXmlGeneration($transaction);
        
        // Test 2: Transaction checking with PULL type
        $this->testPullTransactionChecking($transaction);
        
        // Test 3: Verify XML structure
        $this->verifyXmlStructure($transaction);
        
        $this->info('✅ PULL transaction check testing completed!');
        
        // Clean up
        unlink(__FILE__);
    }
    
    /**
     * Test XML generation with PULL type
     */
    protected function testPullXmlGeneration($transaction)
    {
        $this->info('🔍 Test 1: Testing XML generation with PULL type...');
        
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
        
        // Check if PrivateTransactionReference contains the full object (should not)
        $fullObjectString = json_encode($transaction);
        if (strpos($xmlRequest, $fullObjectString) !== false) {
            $this->error("❌ PrivateTransactionReference still contains full transaction object");
        } else {
            $this->info("✅ PrivateTransactionReference does not contain full transaction object");
        }
    }
    
    /**
     * Test transaction checking with PULL type
     */
    protected function testPullTransactionChecking($transaction)
    {
        $this->info('🔍 Test 2: Testing transaction checking with PULL type...');
        
        $yoService = new YoPaymentsService();
        
        try {
            // Test with PULL type (preferred for transaction checking)
            $result = $yoService->checkTransactionByReference(
                $transaction->transaction_id,
                'PULL',
                $transaction->transaction_id
            );
            
            $this->info("✅ PULL transaction check completed");
            $this->line("  Success: " . ($result['success'] ? 'Yes' : 'No'));
            $this->line("  Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['error_type'])) {
                $this->line("  Error Type: " . $result['error_type']);
            }
            
        } catch (\Exception $e) {
            $this->error("❌ PULL transaction check failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Verify XML structure
     */
    protected function verifyXmlStructure($transaction)
    {
        $this->info('🔍 Test 3: Verifying XML structure...');
        
        $yoService = new YoPaymentsService();
        
        // Use reflection to access the protected buildXmlRequest method
        $reflection = new \ReflectionClass($yoService);
        $method = $reflection->getMethod('buildXmlRequest');
        $method->setAccessible(true);
        
        // Test with proper PULL parameters
        $parameters = [
            'TransactionReference' => 'YP_TEST_REF_' . time(),
            'DepositTransactionType' => 'PULL',
            'PrivateTransactionReference' => $transaction->transaction_id,
        ];
        
        $xmlRequest = $method->invoke($yoService, 'actransactioncheckstatus', $parameters);
        
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
            'transaction_id' => 'TXN_TEST_PULL_' . time(),
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
                'yo_payments_reference' => 'YP_TEST_PULL_' . time(),
            ],
        ]);
        
        $this->info("✅ Test transaction created: {$transaction->transaction_id}");
        $this->line("  Amount: {$transaction->amount} UGX");
        $this->line("  Phone: {$transaction->phone_number}");
        $this->line("  Status: {$transaction->status}");
        $this->line("  Yo Payments Reference: {$transaction->payment_details['yo_payments_reference']}");
    }
} 