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
        // Update existing transactions with new fee structure (100 UGX + 5%)
        $transactions = DB::table('transactions')
            ->whereNotNull('amount')
            ->get();

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            
            // New fee structure: 100 UGX fixed + 5% of amount
            $fixedFee = 100;
            $percentageFee = ($amount * 5) / 100;
            $totalTransactionFee = $fixedFee + $percentageFee;
            $netAmount = $amount - $totalTransactionFee;
            
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'transaction_fee' => round($totalTransactionFee, 2),
                    'net_amount' => round($netAmount, 2),
                    'fee_percentage' => 5.0, // Always 5% now
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous fee structure (tiered percentages)
        $transactions = DB::table('transactions')
            ->whereNotNull('amount')
            ->get();

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            
            // Previous tiered fee structure
            if ($amount >= 1 && $amount <= 5000) {
                $feePercentage = 15.0; // 15% for UGX 1-5000
            } elseif ($amount >= 5001 && $amount <= 6999) {
                $feePercentage = 13.0; // 13% for UGX 5001-6999
            } elseif ($amount >= 7000 && $amount <= 7999) {
                $feePercentage = 11.5; // 11.5% for UGX 7000-7999
            } elseif ($amount >= 8000 && $amount <= 8999) {
                $feePercentage = 11.0; // 11% for UGX 8000-8999
            } elseif ($amount >= 9000 && $amount <= 9999) {
                $feePercentage = 10.0; // 10% for UGX 9000-9999
            } elseif ($amount >= 10000 && $amount <= 10999) {
                $feePercentage = 9.5; // 9.5% for UGX 10000-10999
            } elseif ($amount >= 11000 && $amount <= 19999) {
                $feePercentage = 9.0; // 9% for UGX 11000-19999
            } elseif ($amount >= 20000) {
                $feePercentage = 5.0; // 5% for UGX 20000 and above
            } else {
                $feePercentage = 15.0; // Default 15% for amounts below 1 UGX
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
};