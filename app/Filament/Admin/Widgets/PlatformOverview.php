<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\WithdrawalTransaction;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Platform revenue is both fee streams, as the Blade dashboard had it:
        // the per-sale transaction fee and the withdrawal fee.
        $transactionFees = (float) Transaction::where('status', 'completed')->sum('transaction_fee');
        $withdrawalFees = (float) WithdrawalTransaction::where('status', 'completed')->sum('fee');

        $pendingPayouts = WithdrawalTransaction::where('status', 'pending')->count();

        return [
            Stat::make('Revenue', 'UGX ' . number_format($transactionFees + $withdrawalFees))
                ->description('Transaction and withdrawal fees')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),

            Stat::make('Active tenants', number_format(Tenant::where('is_active', true)->count()))
                ->description(Tenant::count() . ' registered in total')
                ->descriptionIcon(Heroicon::OutlinedUsers),

            // A queue rather than a statistic: each one is somebody waiting
            // for their money.
            Stat::make('Payouts waiting', number_format($pendingPayouts))
                ->description($pendingPayouts > 0 ? 'Awaiting review' : 'Nothing pending')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($pendingPayouts > 0 ? 'warning' : 'gray'),
        ];
    }
}
