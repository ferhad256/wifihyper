<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Approving and rejecting withdrawal requests.
 *
 * Three things the controller version got wrong, all fixed here:
 *
 *  1. It read the status BEFORE opening the transaction, so two admins
 *     pressing approve at the same moment could both pass the check and both
 *     deduct. The status is now re-read inside the transaction with the row
 *     locked.
 *  2. It called decrement() with no check that the balance covered the
 *     amount. A tenant whose balance had fallen since requesting - because an
 *     earlier withdrawal was approved first - went negative. The balance is
 *     now verified under the same lock.
 *  3. Rejection had no server-side requirement for a reason, so a tenant
 *     could be told "rejected" with no explanation. The reason is required by
 *     the caller's schema and asserted here.
 *
 * Email is sent after the transaction commits, and a mail failure never rolls
 * back a completed payout - that behaviour is kept deliberately.
 */
class WithdrawalApprovalService
{
    public function __construct(private EmailService $email)
    {
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function approve(WithdrawalTransaction $withdrawal, Admin $admin, ?string $notes = null): array
    {
        $result = DB::transaction(function () use ($withdrawal, $admin, $notes): array {
            /** @var WithdrawalTransaction|null $fresh */
            $fresh = WithdrawalTransaction::whereKey($withdrawal->getKey())
                ->lockForUpdate()
                ->first();

            if (! $fresh) {
                return ['ok' => false, 'message' => 'That withdrawal request no longer exists.'];
            }

            if ($fresh->status !== 'pending') {
                return ['ok' => false, 'message' => 'This withdrawal request has already been processed.'];
            }

            /** @var Tenant|null $tenant */
            $tenant = Tenant::whereKey($fresh->tenant_id)->lockForUpdate()->first();

            if (! $tenant) {
                return ['ok' => false, 'message' => 'The tenant for this withdrawal no longer exists.'];
            }

            if ((float) $tenant->wallet_balance < (float) $fresh->amount) {
                return [
                    'ok' => false,
                    'message' => 'Their wallet balance (UGX ' . number_format((float) $tenant->wallet_balance)
                        . ') no longer covers this request (UGX ' . number_format((float) $fresh->amount) . ').',
                ];
            }

            $fresh->update([
                'status' => 'completed',
                'admin_id' => $admin->id,
                'admin_notes' => $notes,
                'approved_at' => now(),
                'completed_at' => now(),
            ]);

            $tenant->decrement('wallet_balance', $fresh->amount);

            return ['ok' => true, 'message' => 'Withdrawal approved and deducted from the wallet.', 'tenant' => $tenant, 'withdrawal' => $fresh];
        });

        if ($result['ok']) {
            $this->notify(
                fn () => $this->email->sendWithdrawalApprovalEmail($result['tenant'], $result['withdrawal']),
                $result['withdrawal'],
                'approval'
            );
        }

        return ['ok' => $result['ok'], 'message' => $result['message']];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function reject(WithdrawalTransaction $withdrawal, Admin $admin, string $reason): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            return ['ok' => false, 'message' => 'A reason is required when rejecting a withdrawal.'];
        }

        $result = DB::transaction(function () use ($withdrawal, $admin, $reason): array {
            $fresh = WithdrawalTransaction::whereKey($withdrawal->getKey())
                ->lockForUpdate()
                ->first();

            if (! $fresh) {
                return ['ok' => false, 'message' => 'That withdrawal request no longer exists.'];
            }

            if ($fresh->status !== 'pending') {
                return ['ok' => false, 'message' => 'This withdrawal request has already been processed.'];
            }

            $fresh->update([
                'status' => 'failed',
                'admin_id' => $admin->id,
                'admin_notes' => $reason,
                'rejected_at' => now(),
                'failed_at' => now(),
            ]);

            // No wallet change: the funds were never deducted, since the
            // balance is only touched on approval.
            return ['ok' => true, 'message' => 'Withdrawal rejected.', 'withdrawal' => $fresh];
        });

        if ($result['ok']) {
            $this->notify(
                fn () => $this->email->sendWithdrawalRejectionEmail($result['withdrawal']->tenant, $result['withdrawal']),
                $result['withdrawal'],
                'rejection'
            );
        }

        return ['ok' => $result['ok'], 'message' => $result['message']];
    }

    /**
     * A mail failure must never undo a decision that is already committed.
     */
    private function notify(callable $send, WithdrawalTransaction $withdrawal, string $kind): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error("Failed to send withdrawal {$kind} email", [
                'withdrawal_id' => $withdrawal->id,
                'tenant_id' => $withdrawal->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
