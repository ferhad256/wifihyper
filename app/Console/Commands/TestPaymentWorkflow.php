<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\Hotspot;
use App\Models\Package;
use App\Models\Voucher;
use App\Models\Transaction;
use App\Services\JpesaService;
use App\Services\TransactionFeeService;
use Illuminate\Support\Facades\DB;

class TestPaymentWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:payment-workflow {--cleanup : Clean up test data after test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete payment workflow from initiation to completion';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Complete Payment Workflow');
        $this->newLine();

        try {
            // Step 1: Setup test data
            $this->info('📋 Step 1: Setting up test data...');
            $testData = $this->setupTestData();
            $this->info('✅ Test data created successfully');

            // Step 2: Test payment initiation
            $this->info('💳 Step 2: Testing payment initiation...');
            $transaction = $this->testPaymentInitiation($testData);
            $this->info('✅ Payment initiation successful');

            // Step 3: Test callback processing
            $this->info('📞 Step 3: Testing callback processing...');
            $callbackResult = $this->testCallbackProcessing($transaction);
            $this->info('✅ Callback processing successful');

            // Step 4: Verify workflow completion
            $this->info('🔍 Step 4: Verifying workflow completion...');
            $this->verifyWorkflowCompletion($transaction);

            // Step 5: Display results
            $this->displayResults($transaction);

            // Cleanup if requested
            if ($this->option('cleanup')) {
                $this->info('🧹 Cleaning up test data...');
                $this->cleanupTestData($transaction);
                $this->info('✅ Test data cleaned up');
            }

            $this->newLine();
            $this->info('🎉 Payment workflow test completed successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function setupTestData()
    {
        // Get or create test tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Test Tenant',
                'email' => 'test@example.com',
                'wallet_balance' => 0,
                'subscription_plan_id' => 1
            ]);
        }

        // Get or create test hotspot
        $hotspot = Hotspot::first();
        if (!$hotspot) {
            $hotspot = Hotspot::create([
                'name' => 'Test Hotspot',
                'ssid' => 'TestWiFi',
                'tenant_id' => $tenant->id,
                'is_active' => true
            ]);
        }

        // Get or create test package
        $package = Package::first();
        if (!$package) {
            $package = Package::create([
                'name' => 'Test Package',
                'price' => 1000,
                'duration_hours' => 24,
                'hotspot_id' => $hotspot->id,
                'is_active' => true
            ]);
        }

        // Create test voucher
        $voucher = Voucher::create([
            'code' => 'TEST' . rand(1000, 9999),
            'package_id' => $package->id,
            'tenant_id' => $tenant->id,
            'status' => 'unused'
        ]);

        return [
            'tenant' => $tenant,
            'hotspot' => $hotspot,
            'package' => $package,
            'voucher' => $voucher
        ];
    }

    private function testPaymentInitiation($testData)
    {
        $tenant = $testData['tenant'];
        $hotspot = $testData['hotspot'];
        $package = $testData['package'];
        $voucher = $testData['voucher'];

        // Calculate transaction fees
        $feeService = new TransactionFeeService();
        $feeCalculation = $feeService->calculateFee($package->price);

        // Create transaction (simulating portal form submission)
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'hotspot_id' => $hotspot->id,
            'package_id' => $package->id,
            'voucher_id' => $voucher->id,
            'transaction_id' => 'TXN_TEST_' . time(),
            'amount' => $feeCalculation['amount'],
            'transaction_fee' => $feeCalculation['transaction_fee'],
            'net_amount' => $feeCalculation['net_amount'],
            'fee_percentage' => $feeCalculation['fee_percentage'],
            'currency' => 'UGX',
            'status' => 'pending',
            'phone_number' => '256700000000',
        ]);

        $this->line("   Transaction ID: {$transaction->transaction_id}");
        $this->line("   Amount: UGX {$transaction->amount}");
        $this->line("   Transaction Fee: UGX {$transaction->transaction_fee}");
        $this->line("   Net Amount: UGX {$transaction->net_amount}");
        $this->line("   Phone Number: {$transaction->phone_number}");

        return $transaction;
    }

    private function testCallbackProcessing($transaction)
    {
        // Simulate JPesa callback data
        $callbackData = [
            'tx' => $transaction->transaction_id,
            'tid' => 'JPESA_' . rand(100000, 999999),
            'api_status' => 'success',
            'msg' => '[[S000103]] MM transaction initiated',
            'memo' => '351',
            '_api_log_' => '1596587'
        ];

        $this->line("   Callback TID: {$callbackData['tid']}");
        $this->line("   Callback Status: {$callbackData['api_status']}");

        // Process callback
        $jpesaService = new JpesaService();
        $result = $jpesaService->handleCallback($callbackData);

        if (!$result) {
            throw new \Exception('Callback processing failed');
        }

        return $result;
    }

    private function verifyWorkflowCompletion($transaction)
    {
        $transaction->refresh();
        $voucher = $transaction->voucher;
        $tenant = $transaction->tenant;

        // Verify transaction status
        if ($transaction->status !== 'completed') {
            throw new \Exception("Transaction status is {$transaction->status}, expected 'completed'");
        }

        // Verify voucher status
        if ($voucher->status !== 'used') {
            throw new \Exception("Voucher status is {$voucher->status}, expected 'used'");
        }

        // Verify wallet balance
        $expectedBalance = $tenant->wallet_balance;
        if ($expectedBalance < $transaction->net_amount) {
            throw new \Exception("Wallet balance not updated correctly. Expected at least {$transaction->net_amount}, got {$expectedBalance}");
        }

        // Verify voucher usage timestamp
        if (!$voucher->used_at) {
            throw new \Exception('Voucher used_at timestamp not set');
        }

        $this->line("   Transaction Status: {$transaction->status}");
        $this->line("   Voucher Status: {$voucher->status}");
        $this->line("   Wallet Balance: UGX {$tenant->wallet_balance}");
        $this->line("   Voucher Used At: {$voucher->used_at}");
    }

    private function displayResults($transaction)
    {
        $this->newLine();
        $this->info('📊 Test Results Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Transaction ID', $transaction->transaction_id],
                ['Transaction Status', $transaction->status],
                ['Amount', 'UGX ' . number_format($transaction->amount)],
                ['Transaction Fee', 'UGX ' . number_format($transaction->transaction_fee)],
                ['Net Amount', 'UGX ' . number_format($transaction->net_amount)],
                ['Voucher Code', $transaction->voucher->code],
                ['Voucher Status', $transaction->voucher->status],
                ['Phone Number', $transaction->phone_number],
                ['Paid At', $transaction->paid_at],
                ['Voucher Used At', $transaction->voucher->used_at],
            ]
        );
    }

    private function cleanupTestData($transaction)
    {
        DB::transaction(function () use ($transaction) {
            // Delete transaction
            $transaction->delete();
            
            // Delete voucher
            $transaction->voucher->delete();
            
            // Reset tenant wallet balance
            $tenant = $transaction->tenant;
            $tenant->wallet_balance = 0;
            $tenant->save();
        });
    }
}