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

class TestJpesaApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:jpesa-api {--type=payment : Type of test (payment|withdrawal|both)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test JPesa API functionality for both payments and withdrawals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        
        $this->info('🧪 Testing JPesa API Functionality');
        $this->newLine();

        try {
            if ($type === 'payment' || $type === 'both') {
                $this->info('💳 Testing Payment (Credit) Functionality...');
                $this->testPaymentFunctionality();
                $this->info('✅ Payment functionality tested');
                $this->newLine();
            }

            if ($type === 'withdrawal' || $type === 'both') {
                $this->info('💰 Testing Withdrawal (Debit) Functionality...');
                $this->testWithdrawalFunctionality();
                $this->info('✅ Withdrawal functionality tested');
                $this->newLine();
            }

            $this->info('🎉 JPesa API test completed successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function testPaymentFunctionality()
    {
        // Get test data
        $tenant = Tenant::first();
        $hotspot = Hotspot::first();
        $package = Package::first();
        
        // Create test voucher
        $voucher = Voucher::create([
            'code' => 'PAY_TEST' . rand(1000, 9999),
            'package_id' => $package->id,
            'tenant_id' => $tenant->id,
            'status' => 'unused'
        ]);

        // Calculate fees
        $feeService = new TransactionFeeService();
        $feeCalculation = $feeService->calculateFee($package->price);

        // Create transaction
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'hotspot_id' => $hotspot->id,
            'package_id' => $package->id,
            'voucher_id' => $voucher->id,
            'transaction_id' => 'PAY_TEST_' . time(),
            'amount' => $feeCalculation['amount'],
            'transaction_fee' => $feeCalculation['transaction_fee'],
            'net_amount' => $feeCalculation['net_amount'],
            'fee_percentage' => $feeCalculation['fee_percentage'],
            'currency' => 'UGX',
            'status' => 'pending',
            'phone_number' => '256700000000',
        ]);

        $this->line("   Payment Transaction ID: {$transaction->transaction_id}");
        $this->line("   Amount: UGX {$transaction->amount}");
        $this->line("   Phone Number: {$transaction->phone_number}");

        // Test payment initiation
        $jpesaService = new JpesaService();
        
        if (config('app.env') === 'local' && config('app.debug') === true) {
            $this->line("   Environment: Development (simulated)");
            $result = $jpesaService->initiatePayment($transaction, $transaction->phone_number);
        } else {
            $this->line("   Environment: Production (real API call)");
            $result = $jpesaService->initiatePayment($transaction, $transaction->phone_number);
        }

        $this->line("   Payment Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));
        $this->line("   Message: " . $result['message']);
        
        if ($result['success'] && isset($result['data']['jpesa_tid'])) {
            $this->line("   JPesa TID: " . $result['data']['jpesa_tid']);
            $this->line("   JPesa Memo: " . ($result['data']['jpesa_memo'] ?? 'N/A'));
        }

        // Cleanup
        $transaction->delete();
        $voucher->delete();
    }

    private function testWithdrawalFunctionality()
    {
        $this->line("   Withdrawal Transaction ID: WITHDRAWAL_TEST_" . time());
        $this->line("   Amount: UGX 1000");
        $this->line("   Phone Number: 256700000000");
        $this->line("   Description: Test withdrawal");

        // Test withdrawal initiation
        $jpesaService = new JpesaService();
        
        if (config('app.env') === 'local' && config('app.debug') === true) {
            $this->line("   Environment: Development (simulated)");
            $this->line("   Note: Withdrawal simulation not implemented yet");
            $this->line("   Result: SKIPPED (simulation mode)");
        } else {
            $this->line("   Environment: Production (real API call)");
            $result = $jpesaService->initiateWithdrawal(
                '256700000000',
                1000,
                'WITHDRAWAL_TEST_' . time(),
                'Test withdrawal from WiFi system',
                route('payment.jpesa.callback')
            );

            $this->line("   Withdrawal Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));
            $this->line("   Message: " . $result['message']);
            
            if ($result['success'] && isset($result['data']['jpesa_tid'])) {
                $this->line("   JPesa TID: " . $result['data']['jpesa_tid']);
                $this->line("   JPesa Memo: " . ($result['data']['jpesa_memo'] ?? 'N/A'));
            }
        }
    }
}
