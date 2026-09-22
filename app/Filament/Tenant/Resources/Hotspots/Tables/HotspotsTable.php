<?php

namespace App\Filament\Tenant\Resources\Hotspots\Tables;

use App\Models\Hotspot;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HotspotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Hotspot $record): ?string => $record->location),

                TextColumn::make('ssid')
                    ->label('SSID')
                    ->searchable()
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('packages_count')
                    ->label('Packages')
                    ->counts('packages')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('vouchers_count')
                    ->label('Unused stock')
                    ->counts([
                        'vouchers' => fn ($q) => $q->where('status', 'unused'),
                    ])
                    ->alignEnd()
                    // The number an operator actually needs to watch: no stock
                    // means the portal cannot sell.
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'danger')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Live'),
            ])
            ->recordActions([
                Action::make('viewPortal')
                    ->label('Portal')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Hotspot $record): string => route('portal.index', $record->url_name))
                    ->openUrlInNewTab()
                    ->visible(fn (Hotspot $record): bool => filled($record->url_name)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hotspots yet')
            ->emptyStateDescription('Add a hotspot, give it packages, then upload voucher codes.');
    }
}
