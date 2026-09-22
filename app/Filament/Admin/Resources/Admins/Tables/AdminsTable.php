<?php

namespace App\Filament\Admin\Resources\Admins\Tables;

use App\Models\Admin;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Admin $record): ?string => $record->is(Auth::guard('admin')->user())
                        ? 'You'
                        : null),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'super_admin' ? 'Super admin' : 'Admin')
                    ->color(fn (string $state): string => $state === 'super_admin' ? 'warning' : 'gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Can sign in')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'super_admin' => 'Super admin',
                    ]),
                TernaryFilter::make('is_active')->label('Can sign in'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
