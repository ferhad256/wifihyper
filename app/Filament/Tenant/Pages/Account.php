<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use App\Services\TenantDeletionService;
use App\Services\EmailService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Profile, password, email preference and account closure in one place.
 *
 * Replaces the Blade profile and settings pages. Most of what the old
 * settings page offered has deliberately NOT been carried across, because
 * nothing read it: theme_mode and compact_mode were written to the database
 * but only ever read back from localStorage; sidebar_collapsed,
 * notification_frequency, timezone, low_stock_notifications and
 * transaction_notifications were read by nothing at all; and the entire
 * Security section (two_factor_auth, session_timeout, password_expiry_days)
 * named three features that do not exist, which is worse than offering
 * nothing. email_notifications is kept because EmailService genuinely gates
 * on it.
 *
 * The old "deactivate my own account" toggle is gone too. It was already a
 * foot-gun; now that the middleware re-checks is_active on every request it
 * would lock the operator out permanently with no way back in.
 *
 * Theme and sidebar density are handled natively by the panel, including a
 * System option that - unlike the old "Auto" - actually works.
 */
class Account extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.tenant.pages.account';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public array $profileData = [];

    /** @var array<string, mixed> */
    public array $passwordData = [];

    public function mount(): void
    {
        $tenant = $this->tenant();

        $this->profileForm->fill([
            'name' => $tenant->name,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'business_name' => $tenant->business_name,
            'address' => $tenant->address,
            'email_notifications' => (bool) ($tenant->settings['email_notifications'] ?? false),
        ]);

        $this->passwordForm->fill();
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('profileData')
            ->components([
                Section::make('Your details')
                    ->description('Shown on receipts and used to contact you.')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('tenants', 'email', ignorable: $this->tenant()),

                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Withdrawals can only be sent to this number.'),

                        TextInput::make('business_name')->maxLength(255),

                        Textarea::make('address')->rows(2)->maxLength(500),

                        Toggle::make('email_notifications')
                            ->label('Email me about low stock and payments')
                            ->helperText('Turning this off stops all notification emails.'),
                    ]),
            ]);
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('passwordData')
            ->components([
                Section::make('Password')
                    ->schema([
                        TextInput::make('current_password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword('tenant'),

                        TextInput::make('new_password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->confirmed()
                            ->different('current_password')
                            ->validationMessages([
                                'different' => 'Choose a password different from your current one.',
                            ]),

                        TextInput::make('new_password_confirmation')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public function saveProfile(): void
    {
        $data = $this->profileForm->getState();
        $tenant = $this->tenant();

        $settings = $tenant->settings ?? [];
        $settings['email_notifications'] = (bool) ($data['email_notifications'] ?? false);
        unset($data['email_notifications']);

        $tenant->update([...$data, 'settings' => $settings]);

        Notification::make()->title('Profile saved')->success()->send();
    }

    public function savePassword(): void
    {
        $data = $this->passwordForm->getState();

        // The hashed cast does the hashing.
        $this->tenant()->update([
            'password' => $data['new_password'],
            'password_changed_at' => now(),
        ]);

        $this->passwordForm->fill();

        Notification::make()->title('Password changed')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Send a test email')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->action(function (EmailService $email): void {
                    $result = $email->testEmailConfiguration($this->tenant());

                    Notification::make()
                        ->title($result['success'] ? 'Test email sent' : 'Could not send the test email')
                        ->status($result['success'] ? 'success' : 'danger')
                        ->send();
                }),

            $this->deleteAccountAction(),
        ];
    }

    /**
     * Carries over every guard the Blade version had: the typed phrase, the
     * password, and the refusal while money is still in the wallet.
     */
    private function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label('Close account')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Close your account permanently')
            ->modalDescription('Your hotspots, packages, vouchers, sales history and withdrawals are all deleted. This cannot be undone.')
            ->schema([
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword('tenant')
                    ->label('Your password'),

                TextInput::make('confirmation')
                    ->label('Type DELETE MY ACCOUNT to confirm')
                    ->required()
                    ->rule('in:DELETE MY ACCOUNT')
                    ->validationMessages(['in' => 'Type the phrase exactly as shown.']),
            ])
            ->action(function (TenantDeletionService $deleter) {
                $tenant = $this->tenant();

                if ((float) $tenant->wallet_balance > 0) {
                    Notification::make()
                        ->title('Withdraw your balance first')
                        ->body('UGX ' . number_format((float) $tenant->wallet_balance) . ' is still in your wallet.')
                        ->danger()
                        ->send();

                    return null;
                }

                $deleter->delete($tenant);

                // Facade rather than request()->session(): the session store
                // is not bound on the request in every context this action
                // can run in.
                Auth::guard('tenant')->logout();
                Session::invalidate();
                Session::regenerateToken();

                return redirect()->route('landing');
            });
    }

    private function tenant(): Tenant
    {
        return Auth::guard('tenant')->user();
    }
}
