<?php

namespace App\Filament\Tenant\Resources\WithdrawalTransactions\Pages;

use App\Filament\Tenant\Resources\WithdrawalTransactions\WithdrawalTransactionResource;
use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use App\Services\WithdrawalRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListWithdrawalTransactions extends ListRecords
{
    protected static string $resource = WithdrawalTransactionResource::class;

    public function getSubheading(): ?string
    {
        $tenant = $this->tenant();

        return 'Wallet balance: UGX ' . number_format((float) $tenant->wallet_balance);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request')
                ->label('Request withdrawal')
                ->icon(Heroicon::OutlinedArrowUpOnSquare)
                ->modalHeading('Withdraw to mobile money')
                ->modalDescription(fn (): string => 'Sent to your registered number, '
                    . ($this->tenant()->phone ?: 'which is not set yet')
                    . '. Requests are reviewed within 24 hours.')
                // Hidden rather than erroring when there is already a request
                // in the queue, or no number to send to.
                ->visible(fn (): bool => filled($this->tenant()->phone)
                    && ! WithdrawalTransaction::hasPendingWithdrawal($this->tenant()->id))
                ->schema([
                    TextInput::make('amount')
                        ->label('Amount (UGX)')
                        ->numeric()
                        ->required()
                        ->minValue(WithdrawalRequestService::MINIMUM_AMOUNT)
                        ->maxValue(fn (): float => (float) $this->tenant()->wallet_balance)
                        ->helperText(fn (): string => 'Minimum UGX '
                            . number_format(WithdrawalRequestService::MINIMUM_AMOUNT)
                            . '. A ' . WithdrawalRequestService::FEE_PERCENTAGE . '% fee is deducted.'),

                    TextInput::make('phone_number')
                        ->label('Mobile money number')
                        ->required()
                        ->default(fn (): ?string => $this->tenant()->phone)
                        ->minLength(10)
                        ->maxLength(15)
                        ->helperText('Must match your registered number.'),
                ])
                ->action(function (array $data, WithdrawalRequestService $withdrawals): void {
                    $result = $withdrawals->request(
                        $this->tenant(),
                        (float) $data['amount'],
                        $data['phone_number'],
                    );

                    Notification::make()
                        ->title($result['message'])
                        ->status($result['ok'] ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }

    private function tenant(): Tenant
    {
        return Auth::guard('tenant')->user();
    }
}
