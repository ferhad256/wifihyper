<?php

namespace App\Filament\Tenant\Resources\Transactions;

use App\Filament\Tenant\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Tenant\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only by design.
 *
 * Transactions are written by the payment flow (PaymentController, the JPesa
 * callback and the IPN endpoint). Nothing in the operator's UI may create,
 * edit or delete one - a sales record that can be edited is not a record.
 */
class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'transaction_id';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Sales';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('transactions.tenant_id', Auth::guard('tenant')->id());
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
        ];
    }
}
