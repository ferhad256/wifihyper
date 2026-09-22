<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Transaction;
use App\Support\MonthlyTotals;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class SalesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Sales this year';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $byMonth = MonthlyTotals::sumByMonth(
            Transaction::query()
                ->where('tenant_id', Auth::guard('tenant')->id())
                ->where('status', 'completed'),
            'amount',
            now()->year,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Sales (UGX)',
                    'data' => $byMonth->values()->all(),
                    'borderColor' => '#1C42E0',
                    'backgroundColor' => 'rgba(28, 66, 224, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => MonthlyTotals::labels($byMonth),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
