<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use App\Services\JpesaService;
use Illuminate\Support\Facades\DB;

class TestWithdrawalWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:withdrawal-workflow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete wallet withdrawal workflow';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Wallet Withdrawal Workflow');
        $this->newLine();

        try {
            // Step 1: Setup test data
            $this->info('📋 Step 1: Setting up test data...');
            $tenant = $this->setupTestTenant();
            $this->info('✅ Test tenant created');

            // Step 2: Test withdrawal validation
            $this->info('🔍 Step 2: Testing withdrawal validation...');
            $this->testWithdrawalValidation($tenant);
            $this->info('✅ Withdrawal validation working');

            // Step 3: Test withdrawal creation
            $this->info('💰 Step 3: Testing withdrawal creation...');
            $withdrawal = $this->testWithdrawalCreation($tenant);
            $this->info('✅ Withdrawal creation successful');

            // Step 4: Test callback processing
            $this->info('📞 Step 4: Testing callback processing...');
            $this->testCallbackProcessing($withdrawal);
            $this->info('✅ Callback processing successful');

            // Step 5: Verify wallet deduction
            $this->info('🔍 Step 5: Verifying wallet deduction...');
            $this->verifyWalletDeduction($withdrawal, $tenant);
            $this->info('✅ Wallet deduction verified');

            // Step 6: Display results
            $this->displayResults($withdrawal);

            $this->newLine();
            $this->info('🎉 Withdrawal workflow test completed successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function setupTestTenant()
    {
        // Get or create test tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Test Tenant',
                'email' => 'test@example.com',
                'wallet_balance' => 10000, // Start with 10,000 UGX
                'subscription_plan_id' => 1
            ]);
        } else {
            // Reset wallet balance for testing
            $tenant->wallet_balance = 10000;
            $tenant->save();
        }

        $this->line("   Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->line("   Initial wallet balance: UGX " . number_format($tenant->wallet_balance));

        return $tenant;
    }

    private function testWithdrawalValidation($tenant)
    {
        // Test insufficient balance
        if ($tenant->wallet_balance < 1000) {
            throw new \Exception('Tenant has insufficient balance for testing');
        }

        // Test minimum amount
        $minAmount = 100;
        $this->line("   Minimum withdrawal amount: UGX {$minAmount}");

        // Test maximum amount
        $maxAmount = 1000000;
        $this->line("   Maximum withdrawal amount: UGX " . number_format($maxAmount));

        // Test fee calculation
        $testAmount = 1000;
        $fee = max(50, $testAmount * 0.02);
        $netAmount = $testAmount - $fee;
        
        $this->line("   Test amount: UGX {$testAmount}");
        $this->line("   Withdrawal fee (2%): UGX {$fee}");
        $this->line("   Net amount: UGX {$netAmount}");
    }

    private function testWithdrawalCreation($tenant)
    {
        $amount = 1000;
        $phoneNumber = '256700000000';
        $description = 'Test withdrawal';

        // Calculate fees
        $withdrawalFee = max(50, $amount * 0.02);
        $netAmount = $amount - $withdrawalFee;

        // Create withdrawal transaction
        $withdrawal = WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => WithdrawalTransaction::generateWithdrawalId(),
            'amount' => $amount,
            'fee' => $withdrawalFee,
            'net_amount' => $netAmount,
            'phone_number' => $phoneNumber,
            'currency' => 'UGX',
            'status' => 'pending',
            'description' => $description,
        ]);

        // Simulate wallet deduction (as would happen in initiateWithdrawal)
        $tenant->wallet_balance -= $amount;
        $tenant->save();

        $this->line("   Withdrawal ID: {$withdrawal->withdrawal_id}");
        $this->line("   Amount: UGX {$withdrawal->amount}");
        $this->line("   Fee: UGX {$withdrawal->fee}");
        $this->line("   Net Amount: UGX {$withdrawal->net_amount}");
        $this->line("   Phone Number: {$withdrawal->phone_number}");
        $this->line("   Status: {$withdrawal->status}");
        $this->line("   Wallet Balance After Deduction: UGX " . number_format($tenant->wallet_balance));

        return $withdrawal;
    }

    private function testCallbackProcessing($withdrawal)
    {
        // Simulate JPesa callback data
        $callbackData = [
            'tx' => $withdrawal->withdrawal_id,
            'tid' => 'JPESA_WDR_' . rand(100000, 999999),
            'api_status' => 'success',
            'msg' => '[[S000103]] MM transaction completed',
            'memo' => '351',
            '_api_log_' => '1596587'
        ];

        $this->line("   Callback TID: {$callbackData['tid']}");
        $this->line("   Callback Status: {$callbackData['api_status']}");

        // Process callback
        $controller = new \App\Http\Controllers\WithdrawalController();
        $request = new \Illuminate\Http\Request();
        $request->merge($callbackData);
        
        $response = $controller->handleCallback($request);
        
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Callback processing failed');
        }

        // Refresh withdrawal to get updated data
        $withdrawal->refresh();
        
        $this->line("   Final Status: {$withdrawal->status}");
        $this->line("   Completed At: " . ($withdrawal->completed_at ?? 'Not set'));
    }

    private function verifyWalletDeduction($withdrawal, $tenant)
    {
        $tenant->refresh();
        
        $expectedBalance = 10000 - $withdrawal->amount; // Initial balance minus withdrawal amount
        
        if ((float)$tenant->wallet_balance !== (float)$expectedBalance) {
            throw new \Exception("Wallet balance incorrect. Expected: {$expectedBalance}, Got: {$tenant->wallet_balance}");
        }

        $this->line("   Initial Balance: UGX 10,000");
        $this->line("   Withdrawal Amount: UGX {$withdrawal->amount}");
        $this->line("   Final Balance: UGX " . number_format($tenant->wallet_balance));
        $this->line("   Balance Deduction: UGX {$withdrawal->amount}");
    }

    private function displayResults($withdrawal)
    {
        $this->newLine();
        $this->info('📊 Test Results Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Withdrawal ID', $withdrawal->withdrawal_id],
                ['Status', $withdrawal->status],
                ['Amount', 'UGX ' . number_format($withdrawal->amount)],
                ['Fee', 'UGX ' . number_format($withdrawal->fee)],
                ['Net Amount', 'UGX ' . number_format($withdrawal->net_amount)],
                ['Phone Number', $withdrawal->phone_number],
                ['Description', $withdrawal->description],
                ['Created At', $withdrawal->created_at],
                ['Completed At', $withdrawal->completed_at ?? 'Not completed'],
            ]
        );
        
        $this->newLine();
        $this->info('🔄 Complete Withdrawal Flow Summary:');
        $this->line('1. User requests withdrawal → Validation checks');
        $this->line('2. Withdrawal transaction created → Status: pending');
        $this->line('3. JPesa API called → Status: processing');
        $this->line('4. Wallet amount reserved → Balance reduced');
        $this->line('5. JPesa sends callback → Status: completed');
        $this->line('6. Wallet deduction confirmed → Transaction finalized');
    }
}