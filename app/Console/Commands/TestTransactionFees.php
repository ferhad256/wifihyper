<?php

namespace App\Console\Commands;

use App\Services\TransactionFeeService;
use Illuminate\Console\Command;

class TestTransactionFees extends Command
{
    protected $signature = 'test:transaction-fees';
    protected $description = 'Test the new transaction fee structure';

    public function handle()
    {
        $this->info('Testing New Transaction Fee Structure');
        $this->info('=====================================');
        
        $feeService = new TransactionFeeService();
        
        // Test amounts for each tier
        $testAmounts = [
            1000,   // 15%
            5000,   // 15%
            5001,   // 13%
            6000,   // 13%
            7000,   // 11.5%
            7500,   // 11.5%
            8000,   // 11%
            8500,   // 11%
            9000,   // 10%
            9500,   // 10%
            10000,  // 9.5%
            10500,  // 9.5%
            11000,  // 9%
            15000,  // 9%
            20000,  // 5%
            25000,  // 5%
        ];
        
        foreach ($testAmounts as $amount) {
            $feeCalculation = $feeService->calculateFee($amount);
            
            $this->line(sprintf(
                'UGX %s: Fee %s%% = UGX %s, Net = UGX %s',
                number_format($amount),
                $feeCalculation['fee_percentage'],
                number_format($feeCalculation['transaction_fee']),
                number_format($feeCalculation['net_amount'])
            ));
        }
        
        $this->info('Fee structure test completed!');
    }
}
