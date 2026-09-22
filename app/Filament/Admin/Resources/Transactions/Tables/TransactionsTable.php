<?php

namespace App\Filament\Admin\Resources\Transactions\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('transaction_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hotspot.name')
                    ->label('Hotspot')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('phone_number')
                    ->label('Customer')
                    // The one search the Blade page offered.
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->money('UGX')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('transaction_fee')
                    ->label('Platform fee')
                    ->money('UGX')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success')
                    // The platform's own revenue on this page.
                    ->summarize(Sum::make()->label('Total fees')->money('UGX'))
                    ->sortable(),

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

                SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->emptyStateHeading('No transactions yet');
    }
}
