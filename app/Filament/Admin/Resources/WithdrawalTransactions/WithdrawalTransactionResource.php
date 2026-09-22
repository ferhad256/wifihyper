<?php

namespace App\Filament\Admin\Resources\WithdrawalTransactions;

use App\Filament\Admin\Resources\WithdrawalTransactions\Pages\ListWithdrawalTransactions;
use App\Filament\Admin\Resources\WithdrawalTransactions\Tables\WithdrawalTransactionsTable;
use App\Models\WithdrawalTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The payout queue.
 *
 * Requests are created by tenants; an admin approves or rejects. Neither is
 * editable or deletable here - the only legitimate changes are those two
 * decisions, each of which is an explicit action with its own confirmation.
 */
class WithdrawalTransactionResource extends Resource
{
    protected static ?string $model = WithdrawalTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'withdrawal_id';

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return WithdrawalTransactionsTable::configure($table);
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
            'index' => ListWithdrawalTransactions::route('/'),
        ];
    }

    /**
     * Counts the queue, not the table: a pending payout is someone waiting
     * for their money.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
