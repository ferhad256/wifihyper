<?php

namespace App\Filament\Tenant\Resources\Vouchers;

use App\Filament\Tenant\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Tenant\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Tenant\Resources\Vouchers\Schemas\VoucherForm;
use App\Filament\Tenant\Resources\Vouchers\Tables\VouchersTable;
use App\Models\Voucher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 20;

    /**
     * Scope every query to the signed-in operator.
     *
     * Deliberately explicit here rather than a global scope on the model.
     * Voucher is also read by PaymentController, JpesaService and the captive
     * portal, all of which run unauthenticated - a guard-conditional global
     * scope would be action at a distance on the payment path.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('vouchers.tenant_id', Auth::guard('tenant')->id());
    }

    public static function form(Schema $schema): Schema
    {
        return VoucherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VouchersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('status', 'unused')->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unused vouchers';
    }
}
