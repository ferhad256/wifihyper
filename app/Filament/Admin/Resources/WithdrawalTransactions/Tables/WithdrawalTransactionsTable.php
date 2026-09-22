<?php

namespace App\Filament\Admin\Resources\WithdrawalTransactions\Tables;

use App\Models\Admin;
use App\Models\WithdrawalTransaction;
use App\Services\WithdrawalApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WithdrawalTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('withdrawal_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (WithdrawalTransaction $record): string => $record->phone_number),

                TextColumn::make('tenant.wallet_balance')
                    ->label('Wallet now')
                    ->money('UGX')
                    ->alignEnd()
                    // Shown next to the amount so an admin can see at a glance
                    // whether the balance still covers the request.
                    ->color(fn (WithdrawalTransaction $record): string => (float) ($record->tenant?->wallet_balance ?? 0) >= (float) $record->amount
                        ? 'gray'
                        : 'danger'),

                TextColumn::make('amount')
                    ->money('UGX')
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('UGX')),

                TextColumn::make('fee')
                    ->money('UGX')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('net_amount')
                    ->label('They receive')
                    ->money('UGX')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'failed', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('admin.name')
                    ->label('Handled by')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('admin_notes')
                    ->label('Notes')
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Pending first, oldest first within that: a payout queue, not a
            // reverse-chronological log.
            ->defaultSort('created_at', 'asc')
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw(
                "CASE WHEN status = 'pending' THEN 0 ELSE 1 END"
            ))
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending'),

                SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                self::approveAction(),
                self::rejectAction(),
            ])
            // No bulk approve. Each payout is a separate decision about real
            // money leaving a real wallet.
            ->toolbarActions([]);
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (WithdrawalTransaction $record): bool => $record->status === 'pending')
            ->modalHeading('Approve this withdrawal')
            ->modalDescription(fn (WithdrawalTransaction $record): string => 'UGX '
                . number_format((float) $record->amount)
                . ' will be deducted from ' . ($record->tenant?->name ?? 'the tenant')
                . '\'s wallet. Send the money to ' . $record->phone_number . ' separately.')
            ->schema([
                Textarea::make('admin_notes')
                    ->label('Notes (optional)')
                    ->rows(2),
            ])
            ->action(function (WithdrawalTransaction $record, array $data, WithdrawalApprovalService $service): void {
                /** @var Admin $admin */
                $admin = Auth::guard('admin')->user();

                $result = $service->approve($record, $admin, $data['admin_notes'] ?? null);

                Notification::make()
                    ->title($result['message'])
                    ->status($result['ok'] ? 'success' : 'danger')
                    ->send();
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (WithdrawalTransaction $record): bool => $record->status === 'pending')
            ->modalHeading('Reject this withdrawal')
            ->schema([
                Textarea::make('admin_notes')
                    ->label('Reason')
                    ->rows(3)
                    // Required server-side, not just in the markup: the tenant
                    // is emailed this, and "rejected" with no explanation is
                    // not an acceptable message about someone's money.
                    ->required()
                    ->minLength(3)
                    ->helperText('Sent to the tenant.'),
            ])
            ->action(function (WithdrawalTransaction $record, array $data, WithdrawalApprovalService $service): void {
                /** @var Admin $admin */
                $admin = Auth::guard('admin')->user();

                $result = $service->reject($record, $admin, $data['admin_notes'] ?? '');

                Notification::make()
                    ->title($result['message'])
                    ->status($result['ok'] ? 'success' : 'danger')
                    ->send();
            });
    }
}
