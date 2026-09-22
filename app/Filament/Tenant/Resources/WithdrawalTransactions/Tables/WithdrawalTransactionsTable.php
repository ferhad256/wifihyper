<?php

namespace App\Filament\Tenant\Resources\WithdrawalTransactions\Tables;

use App\Models\WithdrawalTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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

                TextColumn::make('amount')
                    ->money('UGX')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('fee')
                    ->label('Fee')
                    ->money('UGX')
                    ->alignEnd()
                    ->color('warning'),

                TextColumn::make('net_amount')
                    ->label('You receive')
                    ->money('UGX')
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('phone_number')
                    ->label('Sent to')
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
                    // "failed" is the status a rejection is stored under, which
                    // reads like a system error rather than a decision.
                    ->formatStateUsing(fn (string $state, WithdrawalTransaction $record): string => $state === 'failed' && $record->rejected_at
                        ? 'Rejected'
                        : ucfirst($state))
                    ->sortable(),

                TextColumn::make('admin_notes')
                    ->label('Note from us')
                    ->wrap()
                    ->placeholder('-')
                    // Where a rejection reason lands. Worth showing by default:
                    // an operator whose payout was refused should not have to
                    // go looking for why.
                    ->visible(fn ($livewire): bool => true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Rejected or failed',
                    ]),
            ])
            ->emptyStateHeading('No withdrawals yet')
            ->emptyStateDescription('Your sales build up a wallet balance. Withdraw it to your registered phone.');
    }
}
