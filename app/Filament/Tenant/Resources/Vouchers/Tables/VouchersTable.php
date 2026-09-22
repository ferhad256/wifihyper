<?php

namespace App\Filament\Tenant\Resources\Vouchers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Code copied')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('hotspot.name')
                    ->label('Hotspot')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('package.name')
                    ->label('Package')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unused' => 'success',
                        'used' => 'gray',
                        'expired' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->label('Sold to')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('No expiry')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('used_at')
                    ->label('Used')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'unused' => 'Unused',
                        'used' => 'Used',
                        'expired' => 'Expired',
                    ]),

                SelectFilter::make('hotspot_id')
                    ->label('Hotspot')
                    ->options(fn () => Auth::guard('tenant')->user()
                        ->hotspots()
                        ->pluck('name', 'id')
                        ->all()),

                Filter::make('expiring_soon')
                    ->label('Expiring within 7 days')
                    ->query(fn (Builder $q) => $q
                        ->whereNotNull('expires_at')
                        ->whereBetween('expires_at', [now(), now()->addDays(7)])),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No vouchers yet')
            ->emptyStateDescription('Add codes one at a time, paste a batch, or upload a CSV.');
    }
}
