<?php

namespace App\Filament\Tenant\Resources\Vouchers\Schemas;

use App\Models\Package;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class VoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Voucher code')
                    ->required()
                    // Unique across the whole table, not per tenant: the
                    // column carries a global unique index.
                    ->unique(ignoreRecord: true)
                    ->alphaNum()
                    ->minLength(3)
                    ->maxLength(20)
                    ->helperText('3-20 letters and numbers.'),

                Select::make('package_id')
                    ->label('Package')
                    ->options(fn () => self::packageOptions())
                    ->searchable()
                    ->required(),

                DatePicker::make('expires_at')
                    ->label('Expires')
                    ->after('today')
                    ->helperText('Leave blank for no expiry.'),
            ]);
    }

    /**
     * Packages belonging to this tenant, labelled by hotspot.
     *
     * Built from the tenant's own hotspots rather than Package::pluck(), so a
     * package id from another operator can never appear as a choice.
     *
     * @return array<int, string>
     */
    public static function packageOptions(): array
    {
        return Package::query()
            ->whereHas('hotspot', fn ($q) => $q->where('tenant_id', Auth::guard('tenant')->id()))
            ->with('hotspot')
            ->get()
            ->mapWithKeys(fn (Package $p) => [
                $p->id => ($p->hotspot?->name ?? 'No hotspot') . ' - ' . $p->name,
            ])
            ->all();
    }
}
