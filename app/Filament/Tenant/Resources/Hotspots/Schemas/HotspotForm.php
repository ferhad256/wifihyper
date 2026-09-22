<?php

namespace App\Filament\Tenant\Resources\Hotspots\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HotspotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Hotspot name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown to customers at the top of the portal page.'),

                TextInput::make('ssid')
                    ->label('WiFi network name (SSID)')
                    ->required()
                    ->maxLength(255),

                TextInput::make('location')
                    ->maxLength(255)
                    ->placeholder('e.g. Kabalagala'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Accepting customers')
                    ->default(true)
                    ->helperText('Turning this off shows the portal\'s "temporarily unavailable" page.'),
            ]);
    }
}
