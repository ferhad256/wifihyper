<?php

namespace App\Services;

class TransactionFeeService
{
    /**
     * Fee charged on every voucher purchase, as a percentage of the amount.
     */
    public const FEE_PERCENTAGE = 2.8;

    /**
     * Calculate transaction fee based on amount
     * Fee structure: 2.8% of amount
     * 
     * @param float $amount
     * @return array
     */
    public function calculateFee(float $amount): array
    {
        $totalTransactionFee = ($amount * self::FEE_PERCENTAGE) / 100;
        $netAmount = $amount - $totalTransactionFee;

        return [
            'amount' => $amount,
            'transaction_fee' => round($totalTransactionFee, 2),
            'net_amount' => round($netAmount, 2),
            'fee_percentage' => self::FEE_PERCENTAGE,
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
        $totalFee = ($amount * self::FEE_PERCENTAGE) / 100;

        return "Transaction Fee (" . self::FEE_PERCENTAGE . "%): UGX " . number_format($totalFee, 0);
    }
}
