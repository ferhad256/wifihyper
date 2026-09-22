<?php

namespace App\Filament\Admin\Resources\WithdrawalTransactions\Pages;

use App\Filament\Admin\Resources\WithdrawalTransactions\WithdrawalTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawalTransactions extends ListRecords
{
    protected static string $resource = WithdrawalTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
