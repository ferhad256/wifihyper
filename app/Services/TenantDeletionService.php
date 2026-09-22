<?php

namespace App\Services;

use App\Models\Hotspot;
use App\Models\Notification;
use App\Models\Package;
use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\WithdrawalTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a tenant and everything belonging to them.
 *
 * There were two copies of this cascade - one for an admin removing a tenant,
 * one for a tenant closing their own account - and they disagreed:
 *
 *   - the self-service copy matched packages with a scalar subquery
 *     (where('hotspot_id', fn ($q) => ...)), which throws "Subquery returns
 *     more than 1 row" the moment a tenant owns two hotspots, so closing an
 *     account was broken for exactly the operators who used the product most;
 *   - it also never deleted withdrawal records;
 *   - and neither copy deleted SMS logs, leaving rows pointing at a tenant
 *     that no longer exists.
 *
 * One implementation, used by both.
 */
class TenantDeletionService
{
    /**
     * @return array<string, int> rows removed, per table
     */
    public function delete(Tenant $tenant): array
    {
        return DB::transaction(function () use ($tenant): array {
            $hotspotIds = Hotspot::where('tenant_id', $tenant->id)->pluck('id');

            $counts = [
                'notifications' => Notification::where('tenant_id', $tenant->id)->delete(),
                'transactions' => Transaction::where('tenant_id', $tenant->id)->delete(),
                'withdrawals' => WithdrawalTransaction::where('tenant_id', $tenant->id)->delete(),
                'vouchers' => Voucher::where('tenant_id', $tenant->id)->delete(),
                'sms_logs' => SmsLog::where('tenant_id', $tenant->id)->delete(),
                // whereIn, not a scalar subquery: a tenant can own many hotspots.
                'packages' => $hotspotIds->isEmpty()
                    ? 0
                    : Package::whereIn('hotspot_id', $hotspotIds)->delete(),
                'hotspots' => Hotspot::where('tenant_id', $tenant->id)->delete(),
            ];

            $tenant->delete();

            return $counts;
        });
    }
}
