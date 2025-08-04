<?php

namespace App\Services;

class TransactionFeeService
{
    /**
     * Calculate transaction fee based on amount
     * 
     * @param float $amount
     * @return array
     */
    public function calculateFee(float $amount): array
    {
        $feePercentage = $this->getFeePercentage($amount);
        $transactionFee = ($amount * $feePercentage) / 100;
        $netAmount = $amount - $transactionFee;

        return [
            'amount' => $amount,
            'transaction_fee' => round($transactionFee, 2),
            'net_amount' => round($netAmount, 2),
            'fee_percentage' => $feePercentage,
        ];
    }

    /**
     * Get fee percentage based on amount
     * 
     * @param float $amount
     * @return float
     */
    private function getFeePercentage(float $amount): float
    {
        if ($amount <= 1000) {
            return 15.0; // 15% for UGX 1000 and below
        } elseif ($amount <= 5000) {
            return 10.0; // 10% for UGX 1000 to 5000
        } else {
            return 5.0;  // 5% for UGX 5000 and above
        }
    }

    /**
     * Format fee information for display
     * 
     * @param float $amount
     * @return string
     */
    public function getFeeDescription(float $amount): string
    {
        $feePercentage = $this->getFeePercentage($amount);
        $transactionFee = ($amount * $feePercentage) / 100;
        
        return "Transaction Fee ({$feePercentage}%): UGX " . number_format($transactionFee, 0);
    }
} 