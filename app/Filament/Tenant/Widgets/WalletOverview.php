<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Tenant;
use App\Models\Voucher;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class WalletOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        /** @var Tenant $tenant */
        $tenant = Auth::guard('tenant')->user();

        $unusedStock = Voucher::where('tenant_id', $tenant->id)
            ->where('status', 'unused')
            ->count();

        return [
            Stat::make('Wallet balance', 'UGX ' . number_format((float) $tenant->wallet_balance))
                ->description('Available to withdraw')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),

            Stat::make('Today', 'UGX ' . number_format((float) $tenant->today_sales))
                ->description('Sales today')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp),

            // The number that decides whether the portal can sell at all, so
            // it is stated as a problem when it hits zero rather than shown
            // as a neutral figure.
            Stat::make('Voucher stock', number_format($unusedStock))
                ->description($unusedStock === 0
                    ? 'Nothing left to sell - upload codes'
                    : 'Unused codes across all hotspots')
                ->descriptionIcon($unusedStock === 0
                    ? Heroicon::OutlinedExclamationTriangle
                    : Heroicon::OutlinedTicket)
                ->color($unusedStock === 0 ? 'danger' : 'gray'),
        ];
    }
}
