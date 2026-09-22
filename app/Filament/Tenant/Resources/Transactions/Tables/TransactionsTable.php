<?php

namespace App\Filament\Tenant\Resources\Transactions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('transaction_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('hotspot.name')
                    ->label('Hotspot')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('package.name')
                    ->label('Package')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('voucher.code')
                    ->label('Voucher')
                    ->fontFamily('mono')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('phone_number')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->money('UGX')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('transaction_fee')
                    ->label('Fee')
                    ->money('UGX')
                    ->alignEnd()
                    ->color('warning')
                    ->toggleable(),

                TextColumn::make('net_amount')
                    ->label('You keep')
                    ->money('UGX')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success')
                    ->sortable()
                    // Totalled so the figure at the bottom is earnings, not
                    // gross takings.
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->label('Total kept')
                            ->money('UGX')
                    ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('hotspot_id')
                    ->label('Hotspot')
                    ->options(fn () => Auth::guard('tenant')->user()
                        ->hotspots()
                        ->pluck('name', 'id')
                        ->all()),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->emptyStateHeading('No sales yet')
            ->emptyStateDescription('Sales appear here as customers buy vouchers at your hotspots.');
    }
}
