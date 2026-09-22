<?php

namespace App\Filament\Admin\Resources\Transactions;

use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Admin\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Every sale across the platform, read-only.
 *
 * Unscoped: this is the admin console. Written only by the payment flow.
 */
class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $recordTitleAttribute = 'transaction_id';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
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
