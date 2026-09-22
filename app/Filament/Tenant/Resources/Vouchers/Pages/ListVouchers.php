<?php

namespace App\Filament\Tenant\Resources\Vouchers\Pages;

use App\Filament\Tenant\Resources\Vouchers\Schemas\VoucherForm;
use App\Filament\Tenant\Resources\Vouchers\VoucherResource;
use App\Models\Hotspot;
use App\Models\Package;
use App\Services\VoucherImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add voucher')
                ->icon(Heroicon::OutlinedPlus),

            $this->pasteImportAction(),
            $this->csvImportAction(),

            ActionGroup::make([
                $this->exportUnusedAction(),
                $this->deleteAllForPackageAction(),
                $this->deleteAllForHotspotAction(),
            ])
                ->label('More')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->button(),
        ];
    }

    /**
     * Paste a batch of codes: one per line, comma separated, or a mixture.
     */
    private function pasteImportAction(): Action
    {
        return Action::make('importPasted')
            ->label('Paste codes')
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->schema([
                Select::make('package_id')
                    ->label('Package')
                    ->options(fn () => VoucherForm::packageOptions())
                    ->searchable()
                    ->required(),

                Textarea::make('codes')
                    ->label('Voucher codes')
                    ->rows(10)
                    ->required()
                    ->helperText('One per line, or separated by commas. 3-20 letters and numbers each.'),

                DatePicker::make('expires_at')
                    ->label('Expires')
                    ->after('today')
                    ->helperText('Leave blank for no expiry.'),
            ])
            ->action(function (array $data, VoucherImportService $importer): void {
                $package = self::resolvePackage($data['package_id']);

                if (! $package) {
                    self::denied();

                    return;
                }

                $result = $importer->import(
                    Auth::guard('tenant')->user(),
                    $package,
                    $importer->parsePastedCodes($data['codes']),
                    $data['expires_at'] ?? null,
                );

                self::report($result, $importer);
            });
    }

    private function csvImportAction(): Action
    {
        return Action::make('importCsv')
            ->label('Upload CSV')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->schema([
                Select::make('package_id')
                    ->label('Package')
                    ->options(fn () => VoucherForm::packageOptions())
                    ->searchable()
                    ->required(),

                FileUpload::make('csv')
                    ->label('CSV file')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                    ->maxSize(2048)
                    // Kept as a temporary upload so it is never written to
                    // permanent storage just to be read once.
                    ->storeFiles(false)
                    ->required()
                    ->helperText('Codes in the first column. A header row is detected automatically.'),

                DatePicker::make('expires_at')
                    ->label('Expires')
                    ->after('today'),
            ])
            ->action(function (array $data, VoucherImportService $importer): void {
                $package = self::resolvePackage($data['package_id']);

                if (! $package) {
                    self::denied();

                    return;
                }

                $file = $data['csv'];
                $file = is_array($file) ? reset($file) : $file;

                $result = $importer->import(
                    Auth::guard('tenant')->user(),
                    $package,
                    $importer->parseCsv($file->getRealPath()),
                    $data['expires_at'] ?? null,
                );

                self::report($result, $importer);
            });
    }

    private function exportUnusedAction(): Action
    {
        return Action::make('exportUnused')
            ->label('Export unused')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function (): StreamedResponse {
                $vouchers = VoucherResource::getEloquentQuery()
                    ->where('status', 'unused')
                    ->with('package')
                    ->get();

                $filename = 'available_vouchers_' . now()->format('Y-m-d_H-i-s') . '.csv';

                return response()->streamDownload(function () use ($vouchers) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['Voucher Code', 'Package', 'Status', 'Expires At']);

                    foreach ($vouchers as $voucher) {
                        fputcsv($out, [
                            $voucher->code,
                            $voucher->package?->name ?? 'No Package',
                            'Available',
                            $voucher->expires_at?->format('Y-m-d') ?? 'No Expiry',
                        ]);
                    }

                    fclose($out);
                }, $filename, ['Content-Type' => 'text/csv']);
            });
    }

    /**
     * Both bulk deletes remove UNUSED stock only. A used voucher is a sales
     * record and is never destroyed by these.
     */
    private function deleteAllForPackageAction(): Action
    {
        return Action::make('deleteAllForPackage')
            ->label('Delete unused in a package')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->schema([
                Select::make('package_id')
                    ->label('Package')
                    ->options(fn () => VoucherForm::packageOptions())
                    ->searchable()
                    ->required(),

                TextInput::make('confirmation')
                    ->label('Type DELETE to confirm')
                    ->required()
                    ->rule('in:DELETE')
                    ->validationMessages(['in' => 'Type DELETE exactly to confirm.']),
            ])
            ->action(function (array $data): void {
                $package = self::resolvePackage($data['package_id']);

                if (! $package) {
                    self::denied();

                    return;
                }

                $deleted = VoucherResource::getEloquentQuery()
                    ->where('package_id', $package->id)
                    ->where('status', 'unused')
                    ->delete();

                Notification::make()
                    ->title("Deleted {$deleted} unused vouchers from {$package->name}")
                    ->success()
                    ->send();
            });
    }

    private function deleteAllForHotspotAction(): Action
    {
        return Action::make('deleteAllForHotspot')
            ->label('Delete unused at a hotspot')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->schema([
                Select::make('hotspot_id')
                    ->label('Hotspot')
                    ->options(fn () => Auth::guard('tenant')->user()->hotspots()->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),

                TextInput::make('confirmation')
                    ->label('Type DELETE to confirm')
                    ->required()
                    ->rule('in:DELETE')
                    ->validationMessages(['in' => 'Type DELETE exactly to confirm.']),
            ])
            ->action(function (array $data): void {
                $hotspot = Hotspot::where('id', $data['hotspot_id'])
                    ->where('tenant_id', Auth::guard('tenant')->id())
                    ->first();

                if (! $hotspot) {
                    self::denied();

                    return;
                }

                $deleted = VoucherResource::getEloquentQuery()
                    ->where('hotspot_id', $hotspot->id)
                    ->where('status', 'unused')
                    ->delete();

                Notification::make()
                    ->title("Deleted {$deleted} unused vouchers at {$hotspot->name}")
                    ->success()
                    ->send();
            });
    }

    /**
     * Re-check ownership at execution time.
     *
     * The select only ever offers this tenant's packages, but the submitted id
     * is still user input, so it is verified again here rather than trusted.
     */
    private static function resolvePackage(mixed $packageId): ?Package
    {
        return Package::where('id', $packageId)
            ->whereHas('hotspot', fn ($q) => $q->where('tenant_id', Auth::guard('tenant')->id()))
            ->first();
    }

    private static function denied(): void
    {
        Notification::make()->title('Unauthorized action.')->danger()->send();
    }

    /**
     * @param  array{imported:int, skipped:int, invalid:list<string>, errors:list<string>}  $result
     */
    private static function report(array $result, VoucherImportService $importer): void
    {
        Notification::make()
            ->title($importer->summarise($result))
            ->status($result['imported'] > 0 ? 'success' : 'warning')
            ->send();
    }
}
