<?php

namespace App\Filament\Tenant\Resources\Transactions\Pages;

use App\Filament\Tenant\Resources\Transactions\TransactionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /**
     * Same columns as the Blade export, so a tenant's existing spreadsheets
     * and formulas keep working after the cutover.
     *
     * Exports what the current filters select rather than the whole history,
     * which is what someone who has just filtered to a date range expects.
     */
    private function exportCsv(): StreamedResponse
    {
        $transactions = $this->getFilteredTableQuery()
            ->with(['hotspot', 'package', 'voucher'])
            ->orderByDesc('created_at')
            ->get();

        $filename = 'transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Date', 'Transaction ID', 'Hotspot', 'Package', 'Voucher Code',
                'Amount', 'Fee', 'Net Amount', 'Phone Number', 'Status',
            ]);

            foreach ($transactions as $t) {
                fputcsv($out, [
                    $t->created_at->format('Y-m-d H:i:s'),
                    $t->transaction_id,
                    $t->hotspot?->name ?? 'N/A',
                    $t->package?->name ?? 'N/A',
                    $t->voucher?->code ?? 'N/A',
                    $t->amount,
                    $t->transaction_fee,
                    $t->net_amount,
                    $t->phone_number ?? 'N/A',
                    $t->status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
