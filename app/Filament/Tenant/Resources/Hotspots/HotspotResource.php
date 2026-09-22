<?php

namespace App\Filament\Tenant\Resources\Hotspots;

use App\Filament\Tenant\Resources\Hotspots\Pages\CreateHotspot;
use App\Filament\Tenant\Resources\Hotspots\Pages\EditHotspot;
use App\Filament\Tenant\Resources\Hotspots\Pages\ListHotspots;
use App\Filament\Tenant\Resources\Hotspots\RelationManagers\PackagesRelationManager;
use App\Filament\Tenant\Resources\Hotspots\Schemas\HotspotForm;
use App\Filament\Tenant\Resources\Hotspots\Tables\HotspotsTable;
use App\Models\Hotspot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HotspotResource extends Resource
{
    protected static ?string $model = Hotspot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    /**
     * See VoucherResource for why scoping is explicit here rather than a
     * global scope: Hotspot is read unauthenticated by the captive portal.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('hotspots.tenant_id', Auth::guard('tenant')->id());
    }

    public static function form(Schema $schema): Schema
    {
        return HotspotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HotspotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PackagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHotspots::route('/'),
            'create' => CreateHotspot::route('/create'),
            'edit' => EditHotspot::route('/{record}/edit'),
        ];
    }
}
