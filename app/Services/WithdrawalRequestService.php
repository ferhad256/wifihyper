<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A tenant asking to withdraw their wallet balance to mobile money.
 *
 * Lifted out of DashboardController::withdraw so the Blade page and the panel
 * share one implementation, and so the guards are testable on their own. The
 * rules are carried over unchanged - this is live money handling and now was
 * not the moment to redesign them:
 *
 *   - UGX 5,000 minimum;
 *   - never more than the wallet holds;
 *   - one open request at a time;
 *   - the destination must be the tenant's own registered number.
 *
 * That last one is the important one. It is what stops a withdrawal being
 * redirected to somebody else's phone, and it is the check the dead
 * WithdrawalController path did not have.
 */
class WithdrawalRequestService
{
    public const MINIMUM_AMOUNT = 5000;

    /** Withdrawal fee, as a percentage of the amount. */
    public const FEE_PERCENTAGE = 5;

    public function __construct(private EgoSmsService $sms)
    {
    }

    /**
     * @return array{ok: bool, message: string, withdrawal?: WithdrawalTransaction}
     */
    public function request(Tenant $tenant, float $amount, string $phoneNumber): array
    {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        if ($amount < self::MINIMUM_AMOUNT) {
            return ['ok' => false, 'message' => 'Minimum withdrawal amount is UGX ' . number_format(self::MINIMUM_AMOUNT) . '.'];
        }

        if ($amount > (float) $tenant->wallet_balance) {
            return ['ok' => false, 'message' => 'Withdrawal amount cannot exceed your wallet balance.'];
        }

        if (WithdrawalTransaction::hasPendingWithdrawal($tenant->id)) {
            $pending = WithdrawalTransaction::getPendingWithdrawal($tenant->id);

            return [
                'ok' => false,
                'message' => 'You already have a withdrawal request awaiting review (' . $pending->withdrawal_id . ').',
            ];
        }

        if (! $tenant->phone) {
            return ['ok' => false, 'message' => 'Add a phone number to your profile before requesting a withdrawal.'];
        }

        if ($phoneNumber !== $this->formatPhoneNumber($tenant->phone)) {
            return [
                'ok' => false,
                'message' => 'Withdrawals can only be sent to your own registered phone number.',
            ];
        }

        if (! $this->isValidUgandaPhoneNumber($phoneNumber)) {
            return ['ok' => false, 'message' => 'Please enter a valid Uganda phone number.'];
        }

        $fee = $amount * (self::FEE_PERCENTAGE / 100);

        $withdrawal = DB::transaction(fn () => WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . time() . '_' . random_int(1000, 9999),
            'amount' => $amount,
            'fee' => $fee,
            'net_amount' => $amount - $fee,
            'phone_number' => $phoneNumber,
            'currency' => 'UGX',
            'status' => 'pending',
            'description' => 'Wallet withdrawal request',
        ]));

        Log::info('Withdrawal request created', [
            'withdrawal_id' => $withdrawal->withdrawal_id,
            'tenant_id' => $tenant->id,
            'amount' => $amount,
        ]);

        $this->alertAdmin($tenant, $withdrawal);

        return [
            'ok' => true,
            'message' => 'Withdrawal request submitted. An admin reviews requests within 24 hours.',
            'withdrawal' => $withdrawal,
        ];
    }

    /**
     * The wallet is NOT debited here. Funds move only when an admin approves,
     * which is why a rejected request needs nothing returned.
     */
    private function alertAdmin(Tenant $tenant, WithdrawalTransaction $withdrawal): void
    {
        $adminPhone = config('services.admin_alert_phone');

        if (! $adminPhone) {
            return;
        }

        try {
            $this->sms->sendSms(
                $adminPhone,
                "New withdrawal request from {$tenant->name}: UGX "
                    . number_format((float) $withdrawal->amount)
                    . " (ID: {$withdrawal->withdrawal_id})"
            );
        } catch (\Throwable $e) {
            // A failed alert must not fail the request the tenant just made.
            Log::error('Failed to send admin SMS for withdrawal request', [
                'withdrawal_id' => $withdrawal->withdrawal_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function formatPhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (str_starts_with($phoneNumber, '0')) {
            return '256' . substr($phoneNumber, 1);
        }

        if (! str_starts_with($phoneNumber, '256')) {
            return '256' . $phoneNumber;
        }

        return $phoneNumber;
    }

    public function isValidUgandaPhoneNumber(string $phoneNumber): bool
    {
        return (bool) preg_match('/^256[0-9]{9}$/', $phoneNumber);
    }
}
