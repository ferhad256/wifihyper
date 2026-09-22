<?php

namespace App\Filament\Admin\Resources\Admins\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->confirmed()
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? 'At least 8 characters.'
                        : 'Leave blank to keep the current password.')
                    // Blank on edit means "unchanged" rather than "set empty".
                    // The model's hashed cast does the hashing.
                    ->dehydrated(fn (?string $state): bool => filled($state)),

                TextInput::make('password_confirmation')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(false),

                Select::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'super_admin' => 'Super admin',
                    ])
                    ->default('admin')
                    ->required()
                    ->selectablePlaceholder(false)
                    ->helperText('Super admins can manage staff accounts.')
                    // Stops the last super admin demoting themselves and
                    // locking everyone out of staff management.
                    ->disabled(fn (?\App\Models\Admin $record): bool => $record?->is(Auth::guard('admin')->user()) ?? false),

                Toggle::make('is_active')
                    ->label('Can sign in')
                    ->default(true)
                    ->disabled(fn (?\App\Models\Admin $record): bool => $record?->is(Auth::guard('admin')->user()) ?? false)
                    ->helperText(fn (?\App\Models\Admin $record): ?string => $record?->is(Auth::guard('admin')->user())
                        ? 'You cannot deactivate your own account.'
                        : null),
            ]);
    }
}
