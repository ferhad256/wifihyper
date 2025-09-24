<?php

namespace App\Services;

class TransactionFeeService
{
    /**
     * Calculate transaction fee based on amount
     * New structure: 100 UGX fixed fee + 5% of amount
     * 
     * @param float $amount
     * @return array
     */
    public function calculateFee(float $amount): array
    {
        $fixedFee = 100; // Fixed 100 UGX fee
        $percentageFee = ($amount * 5) / 100; // 5% of amount
        $totalTransactionFee = $fixedFee + $percentageFee;
        $netAmount = $amount - $totalTransactionFee;

        return [
            'amount' => $amount,
            'transaction_fee' => round($totalTransactionFee, 2),
            'net_amount' => round($netAmount, 2),
            'fee_percentage' => 5.0, // Always 5%
            'fixed_fee' => $fixedFee,
            'percentage_fee' => round($percentageFee, 2),
        ];
    }

    /**
     * Format fee information for display
     * 
     * @param float $amount
     * @return string
     */
    public function getFeeDescription(float $amount): string
    {
        $fixedFee = 100;
        $percentageFee = ($amount * 5) / 100;
        $totalFee = $fixedFee + $percentageFee;
        
        return "Transaction Fee (100 UGX + 5%): UGX " . number_format($totalFee, 0);
    }
} 