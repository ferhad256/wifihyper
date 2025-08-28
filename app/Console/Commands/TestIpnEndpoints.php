<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\YoPaymentsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TestIpnEndpoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ipn-endpoints {--transaction-id=} {--create-test-data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test IPN endpoints and payment workflow completion for Yo Payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing IPN Endpoints and Payment Workflow...');
        
        // Check if we should create test data
        if ($this->option('create-test-data')) {
            $this->createTestData();
        }
        
        // Test configuration
        $this->testConfiguration();
        
        // Test notification URL generation
        $this->testNotificationUrls();
        
        // Test IPN endpoint accessibility
        $this->testEndpointAccessibility();
        
        // Test payment workflow simulation
        $this->testPaymentWorkflow();
        
        $this->info('✅ IPN endpoint testing completed!');
        
        // Clean up
        unlink(__FILE__);
    }
    
    /**
     * Test configuration settings
     */
    protected function testConfiguration()
    {
        $this->info('📋 Testing Configuration...');
        
        $config = [
            'IPN Success URL' => config('services.yo_payments.ipn_urls.success'),
            'IPN Failure URL' => config('services.yo_payments.ipn_urls.failure'),
            'IPN Pending URL' => config('services.yo_payments.ipn_urls.pending'),
            'IPN Enabled' => config('services.yo_payments.ipn_enabled') ? 'Yes' : 'No',
            'IPN Timeout' => config('services.yo_payments.ipn_timeout'),
            'IPN Retry Attempts' => config('services.yo_payments.ipn_retry_attempts'),
        ];
        
        $this->table(['Setting', 'Value'], collect($config)->map(function ($value, $key) {
            return [$key, $value];
        })->toArray());
        
        // Validate URLs
        foreach (['success', 'failure', 'pending'] as $type) {
            $url = config("services.yo_payments.ipn_urls.{$type}");
            if (!$url) {
                $this->warn("⚠️  IPN {$type} URL not configured");
            } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->error("❌ Invalid IPN {$type} URL: {$url}");
            } else {
                $this->info("✅ IPN {$type} URL: {$url}");
            }
        }
    }
    
    /**
     * Test notification URL generation
     */
    protected function testNotificationUrls()
    {
        $this->info('🔗 Testing Notification URL Generation...');
        
        $yoService = new YoPaymentsService();
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($yoService);
        $method = $reflection->getMethod('buildNotificationUrl');
        $method->setAccessible(true);
        
        $testCases = [
            'success' => [],
            'failure' => [],
            'success_with_params' => [
                'notification_params' => [
                    'source' => 'yo_payments',
                    'type' => 'success',
                    'transaction_id' => 'TXN_TEST_123'
                ]
            ]
        ];
        
        foreach ($testCases as $type => $params) {
            $url = $method->invoke($yoService, $type, $params);
            $this->line("{$type}: {$url}");
            
            // Check XML escaping
            if (strpos($url, '&amp;') !== false) {
                $this->info("  ✅ XML escaping applied");
            }
        }
    }
    
    /**
     * Test endpoint accessibility
     */
    protected function testEndpointAccessibility()
    {
        $this->info('🌐 Testing Endpoint Accessibility...');
        
        $baseUrl = config('app.url') ?: 'http://localhost:8000';
        $endpoints = [
            'payment/callback' => 'POST',
            'payment/failed' => 'POST',
        ];
        
        foreach ($endpoints as $endpoint => $method) {
            $url = rtrim($baseUrl, '/') . '/' . $endpoint;
            
            try {
                if ($method === 'POST') {
                    $response = Http::post($url, [
                        'test' => 'data',
                        'timestamp' => now()->toISOString()
                    ]);
                } else {
                    $response = Http::get($url);
                }
                
                if ($response->successful()) {
                    $this->info("✅ {$endpoint} ({$method}): Accessible - Status {$response->status()}");
                } else {
                    $this->warn("⚠️  {$endpoint} ({$method}): Accessible but returned status {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->error("❌ {$endpoint} ({$method}): Not accessible - {$e->getMessage()}");
            }
        }
    }
    
    /**
     * Test payment workflow simulation
     */
    protected function testPaymentWorkflow()
    {
        $this->info('💳 Testing Payment Workflow Simulation...');
        
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
        
        // Test callback processing
        $this->testCallbackProcessing($transaction);
        
        // Test failure notification
        $this->testFailureNotification($transaction);
    }
    
    /**
     * Test callback processing
     */
    protected function testCallbackProcessing($transaction)
    {
        $this->info('📞 Testing Callback Processing...');
        
        $yoService = new YoPaymentsService();
        
        // Simulate successful payment callback
        $successCallbackData = http_build_query([
            'external_ref' => $transaction->transaction_id,
            'Status' => 'OK',
            'Amount' => $transaction->amount,
            'Currency' => 'UGX',
            'IssuedReceiptNumber' => 'REC_' . time(),
            'TransactionInitiationDate' => now()->format('Y-m-d H:i:s'),
            'TransactionCompletionDate' => now()->format('Y-m-d H:i:s'),
        ]);
        
        $result = $yoService->processCallback($successCallbackData);
        
        if ($result['success']) {
            $this->info("✅ Callback processed successfully");
            $this->line("  Transaction ID: {$result['transaction_id']}");
            $this->line("  Status: {$result['status']}");
            
            // Refresh transaction
            $transaction->refresh();
            $this->line("  New Status: {$transaction->status}");
        } else {
            $this->error("❌ Callback processing failed: {$result['message']}");
        }
    }
    
    /**
     * Test failure notification
     */
    protected function testFailureNotification($transaction)
    {
        $this->info('❌ Testing Failure Notification...');
        
        // Simulate failure notification
        $failureData = [
            'failed_transaction_reference' => $transaction->transaction_id,
            'transaction_init_date' => now()->format('Y-m-d H:i:s'),
            'verification' => 'test_verification_' . time(),
        ];
        
        // Test the failed endpoint
        $baseUrl = config('app.url') ?: 'http://localhost:8000';
        $url = rtrim($baseUrl, '/') . '/payment/failed';
        
        try {
            $response = Http::post($url, $failureData);
            
            if ($response->successful()) {
                $this->info("✅ Failure notification processed successfully");
                $this->line("  Status: {$response->status()}");
                $this->line("  Response: {$response->body()}");
            } else {
                $this->warn("⚠️  Failure notification returned status {$response->status()}");
                $this->line("  Response: {$response->body()}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Failure notification failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Create test data for testing
     */
    protected function createTestData()
    {
        $this->info('📝 Creating Test Data...');
        
        // Find or create a test tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->warn('⚠️  No tenants found. Please create a tenant first.');
            return;
        }
        
        // Find or create a test package
        $package = Package::first();
        if (!$package) {
            $this->warn('⚠️  No packages found. Please create a package first.');
            return;
        }
        
        // Create a test transaction
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'transaction_id' => 'TXN_TEST_' . time(),
            'amount' => 500,
            'net_amount' => 425,
            'fee_amount' => 75,
            'phone_number' => '256783052764',
            'status' => 'pending',
            'payment_method' => 'mobile_money',
            'payment_details' => [
                'test_transaction' => true,
                'created_at' => now()->toISOString(),
                'provider' => 'MTN',
            ],
        ]);
        
        $this->info("✅ Test transaction created: {$transaction->transaction_id}");
        $this->line("  Amount: {$transaction->amount} UGX");
        $this->line("  Phone: {$transaction->phone_number}");
        $this->line("  Status: {$transaction->status}");
    }
} 