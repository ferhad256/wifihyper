<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Transaction;
use App\Models\WithdrawalTransaction;
use App\Support\MonthlyTotals;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Fee revenue this year';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $year = now()->year;

        $transactionFees = MonthlyTotals::sumByMonth(
            Transaction::query()->where('status', 'completed'),
            'transaction_fee',
            $year,
        );

        $withdrawalFees = MonthlyTotals::sumByMonth(
            WithdrawalTransaction::query()->where('status', 'completed'),
            'fee',
            $year,
        );

        return [
            'datasets' => [
                // Stacked rather than summed into one line, so it is visible
                // which stream the revenue came from.
                [
                    'label' => 'Transaction fees',
                    'data' => $transactionFees->values()->all(),
                    'backgroundColor' => '#1C42E0',
                    'stack' => 'fees',
                ],
                [
                    'label' => 'Withdrawal fees',
                    'data' => $withdrawalFees->values()->all(),
                    'backgroundColor' => '#A4B6FF',
                    'stack' => 'fees',
                ],
            ],
            'labels' => MonthlyTotals::labels($transactionFees),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }
}
