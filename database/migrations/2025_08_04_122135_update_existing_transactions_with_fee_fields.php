<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing transactions that don't have fee fields populated
        $transactions = DB::table('transactions')
            ->whereNull('transaction_fee')
            ->orWhere('transaction_fee', 0)
            ->get();

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            
            // Calculate fee percentage based on amount
            if ($amount <= 1000) {
                $feePercentage = 15.0; // 15% for UGX 1000 and below
            } elseif ($amount <= 5000) {
                $feePercentage = 10.0; // 10% for UGX 1000 to 5000
            } else {
                $feePercentage = 5.0;  // 5% for UGX 5000 and above
            }
            
            $transactionFee = ($amount * $feePercentage) / 100;
            $netAmount = $amount - $transactionFee;
            
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'transaction_fee' => round($transactionFee, 2),
                    'net_amount' => round($netAmount, 2),
                    'fee_percentage' => $feePercentage,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this migration as it's just updating existing data
    }
};
