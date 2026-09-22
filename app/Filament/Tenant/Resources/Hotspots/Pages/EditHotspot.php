<?php

namespace App\Filament\Tenant\Resources\Hotspots\Pages;

use App\Filament\Tenant\Resources\Hotspots\HotspotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHotspot extends EditRecord
{
    protected static string $resource = HotspotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
