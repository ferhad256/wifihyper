<?php

namespace App\Filament\Tenant\Resources\Vouchers\Pages;

use App\Filament\Tenant\Resources\Vouchers\VoucherResource;
use App\Models\Package;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;

    /**
     * The owning tenant and hotspot are derived rather than submitted: the
     * hotspot comes from the chosen package, matching how bulk import works.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Auth::guard('tenant')->id();
        $data['status'] = 'unused';
        $data['hotspot_id'] = Package::find($data['package_id'])?->hotspot_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
