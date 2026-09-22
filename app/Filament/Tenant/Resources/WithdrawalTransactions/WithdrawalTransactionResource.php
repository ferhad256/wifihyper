<?php

namespace App\Filament\Tenant\Resources\WithdrawalTransactions;

use App\Filament\Tenant\Resources\WithdrawalTransactions\Pages\ListWithdrawalTransactions;
use App\Filament\Tenant\Resources\WithdrawalTransactions\Tables\WithdrawalTransactionsTable;
use App\Models\WithdrawalTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The operator's own withdrawal history and the button to request a new one.
 *
 * Requesting is a header action rather than a create page: a withdrawal is
 * not a record the tenant authors, it is a request whose amount, fee and
 * destination are all derived and checked server-side.
 */
class WithdrawalTransactionResource extends Resource
{
    protected static ?string $model = WithdrawalTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpOnSquare;

    protected static ?string $recordTitleAttribute = 'withdrawal_id';

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?int $navigationSort = 40;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('withdrawal_transactions.tenant_id', Auth::guard('tenant')->id());
    }

    public static function table(Table $table): Table
    {
        return WithdrawalTransactionsTable::configure($table);
    }

    // A tenant may ask for a withdrawal, but never edit, delete, or otherwise
    // alter one once asked - including their own pending request.
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
}
