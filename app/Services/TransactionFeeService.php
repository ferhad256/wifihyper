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
        if ($amount >= 1 && $amount <= 5000) {
            return 15.0; // 15% for UGX 1-5000
        } elseif ($amount >= 5001 && $amount <= 6999) {
            return 13.0; // 13% for UGX 5001-6999
        } elseif ($amount >= 7000 && $amount <= 7999) {
            return 11.5; // 11.5% for UGX 7000-7999
        } elseif ($amount >= 8000 && $amount <= 8999) {
            return 11.0; // 11% for UGX 8000-8999
        } elseif ($amount >= 9000 && $amount <= 9999) {
            return 10.0; // 10% for UGX 9000-9999
        } elseif ($amount >= 10000 && $amount <= 10999) {
            return 9.5; // 9.5% for UGX 10000-10999
        } elseif ($amount >= 11000 && $amount <= 19999) {
            return 9.0; // 9% for UGX 11000-19999
        } elseif ($amount >= 20000) {
            return 5.0; // 5% for UGX 20000 and above
        } else {
            return 15.0; // Default 15% for amounts below 1 UGX
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