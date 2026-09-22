<?php

namespace App\Filament\Admin\Resources\Tenants\Tables;

use App\Models\Tenant;
use App\Services\TenantDeletionService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Tenant $record): ?string => $record->business_name),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('hotspots_count')
                    ->label('Hotspots')
                    ->counts('hotspots')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('wallet_balance')
                    ->label('Wallet')
                    ->money('UGX')
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable(),
            ])
            ->recordActions([
                self::toggleStatusAction(),
                self::deleteWithDataAction(),
            ])
            // No bulk delete. Removing a tenant destroys their sales history
            // as well, so it should never be something done to a checkbox
            // selection by accident.
            ->toolbarActions([]);
    }

    private static function toggleStatusAction(): Action
    {
        return Action::make('toggleStatus')
            ->label(fn (Tenant $record): string => $record->is_active ? 'Deactivate' : 'Activate')
            ->icon(fn (Tenant $record): Heroicon => $record->is_active
                ? Heroicon::OutlinedPauseCircle
                : Heroicon::OutlinedPlayCircle)
            ->color(fn (Tenant $record): string => $record->is_active ? 'warning' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (Tenant $record): string => $record->is_active
                ? 'They will be signed out and cannot sign in again until reactivated. Their hotspots stop selling.'
                : 'They will be able to sign in again and their hotspots resume selling.')
            ->action(function (Tenant $record): void {
                $record->update(['is_active' => ! $record->is_active]);

                Notification::make()
                    ->title($record->is_active ? "{$record->name} activated" : "{$record->name} deactivated")
                    ->success()
                    ->send();
            });
    }

    /**
     * Permanently removes the tenant and everything belonging to them.
     *
     * The Blade version was guarded only by a browser confirm(). Given this
     * destroys sales history irreversibly, it now requires the operator's
     * email to be typed out, matching the rigour of the tenant-side account
     * deletion.
     */
    private static function deleteWithDataAction(): Action
    {
        return Action::make('deleteWithData')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Permanently delete this tenant')
            ->modalDescription('Hotspots, packages, vouchers, sales, withdrawals, SMS logs and alerts are all destroyed. This cannot be undone.')
            ->schema([
                TextInput::make('confirmation')
                    ->label(fn (Tenant $record): string => "Type {$record->email} to confirm")
                    ->required()
                    ->rule(fn (Tenant $record) => 'in:' . $record->email)
                    ->validationMessages(['in' => 'That does not match the tenant\'s email address.']),
            ])
            ->action(function (Tenant $record, TenantDeletionService $deleter): void {
                $name = $record->name;
                $counts = $deleter->delete($record);

                Notification::make()
                    ->title("Deleted {$name}")
                    ->body(collect($counts)
                        ->filter()
                        ->map(fn (int $n, string $table): string => "{$n} {$table}")
                        ->implode(', ') ?: 'No related records.')
                    ->success()
                    ->send();
            });
    }
}
