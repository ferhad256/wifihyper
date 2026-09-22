<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\WithdrawalTransaction;
use App\Models\Hotspot;
use App\Models\Voucher;
use App\Models\Package;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\SmsLog;

class AdminDashboardController extends Controller
{
    /**
     * Show admin dashboard
     */
    public function index()
    {
        // Get overall statistics
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'total_hotspots' => Hotspot::count(),
            'total_vouchers' => Voucher::count(),
            'total_transactions' => Transaction::count(),
            'total_revenue' => Tenant::sum('wallet_balance'),
            'pending_withdrawals' => WithdrawalTransaction::where('status', 'pending')->count(),
            'pending_withdrawal_amount' => WithdrawalTransaction::where('status', 'pending')->sum('amount'),
            // Transaction fee analytics (Owner's Profit) - includes both transaction fees and withdrawal fees
            'total_fees' => Transaction::where('status', 'completed')->sum('transaction_fee') + 
                           WithdrawalTransaction::where('status', 'completed')->sum('fee'),
            'today_fees' => Transaction::where('status', 'completed')->whereDate('created_at', today())->sum('transaction_fee') + 
                           WithdrawalTransaction::where('status', 'completed')->whereDate('created_at', today())->sum('fee'),
            'this_week_fees' => Transaction::where('status', 'completed')->where('created_at', '>=', now()->subDays(7))->sum('transaction_fee') + 
                               WithdrawalTransaction::where('status', 'completed')->where('created_at', '>=', now()->subDays(7))->sum('fee'),
            'this_month_fees' => Transaction::where('status', 'completed')->where('created_at', '>=', now()->startOfMonth())->sum('transaction_fee') + 
                                WithdrawalTransaction::where('status', 'completed')->where('created_at', '>=', now()->startOfMonth())->sum('fee'),
        ];

        // Get monthly fee data for chart (current year from January to December) - combining transaction fees and withdrawal fees
        $currentYear = now()->year;
        $transaction_fees = Transaction::where('status', 'completed')
            ->whereYear('created_at', $currentYear)
            ->selectRaw(\App\Support\MonthlyTotals::monthExpression() . ' as month, SUM(transaction_fee) as fees, COUNT(*) as count')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $withdrawal_fees = WithdrawalTransaction::where('status', 'completed')
            ->whereYear('created_at', $currentYear)
            ->selectRaw(\App\Support\MonthlyTotals::monthExpression() . ' as month, SUM(fee) as fees, COUNT(*) as count')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Combine both fee sources by month for the current year
        $all_months = collect();
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = sprintf('%04d-%02d', $currentYear, $month);
            $transaction_fee = $transaction_fees->get($monthStr)?->fees ?? 0;
            $withdrawal_fee = $withdrawal_fees->get($monthStr)?->fees ?? 0;
            $total_fees = $transaction_fee + $withdrawal_fee;
            
            $all_months->push([
                'month' => $monthStr,
                'total_fees' => (float) $total_fees,
                'transaction_fees' => (float) $transaction_fee,
                'withdrawal_fees' => (float) $withdrawal_fee,
                'formatted_date' => date('M Y', strtotime($monthStr . '-01'))
            ]);
        }

        $filled_fee_data = $all_months;

        // Get recent tenants
        $recent_tenants = Tenant::latest()->take(5)->get();

        // Get recent transactions
        $recent_transactions = Transaction::with(['tenant', 'hotspot', 'package'])
            ->latest()
            ->take(10)
            ->get();

        // Get pending withdrawal requests
        $pending_withdrawals = WithdrawalTransaction::with('tenant')
            ->where('status', 'pending')
            ->latest()
            ->take(10)
            ->get();

        // SMS usage stats (last 14 days) and today's total
        $days = 14;
        $dates = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates->push(now()->copy()->subDays($i)->startOfDay());
        }

        $smsByDate = SmsLog::whereNotNull('sent_at')
            ->where('status', 'sent')
            ->where('sent_at', '>=', $dates->first())
            ->selectRaw(\App\Support\MonthlyTotals::dayExpression('sent_at') . ' as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $sms_daily = $dates->map(function ($date) use ($smsByDate) {
            $key = $date->format('Y-m-d');
            return [
                'date' => $date->format('M d'),
                'count' => (int) ($smsByDate[$key] ?? 0),
            ];
        });

        $sms_today = SmsLog::whereNotNull('sent_at')
            ->where('status', 'sent')
            ->whereDate('sent_at', now()->toDateString())
            ->count();

        return view('admin.dashboard.index', compact('stats', 'recent_tenants', 'recent_transactions', 'pending_withdrawals', 'filled_fee_data', 'sms_daily', 'sms_today'));
    }

    /**
     * Show tenants management
     */
    public function tenants()
    {
        $tenants = Tenant::with(['hotspots', 'transactions'])
            ->withCount(['hotspots', 'transactions'])
            ->paginate(20);

        return view('admin.dashboard.tenants', compact('tenants'));
    }

    /**
     * Show tenant details
     */
    public function showTenant($id)
    {
        $tenant = Tenant::with([
            'hotspots.packages', 
            'hotspots.vouchers',
            'transactions.hotspot', 
            'transactions.package',
            'vouchers.package',
            'vouchers.hotspot'
        ])->findOrFail($id);

        // Enhanced tenant statistics
        $tenant_stats = [
            'total_sales' => $tenant->transactions()->where('status', 'completed')->sum('amount') - 
                             $tenant->withdrawalTransactions()->where('status', 'completed')->sum('amount'),
            'total_transactions' => $tenant->transactions()->count(),
            'total_hotspots' => $tenant->hotspots()->count(),
            'total_vouchers' => $tenant->vouchers()->count(),
            'used_vouchers' => $tenant->vouchers()->where('status', 'used')->count(),
            'unused_vouchers' => $tenant->vouchers()->where('status', 'unused')->count(),
            'expired_vouchers' => $tenant->vouchers()->where('status', 'expired')->count(),
            'active_hotspots' => $tenant->hotspots()->where('is_active', true)->count(),
            'inactive_hotspots' => $tenant->hotspots()->where('is_active', false)->count(),
            'wallet_balance' => $tenant->wallet_balance,
        ];

        // Get voucher statistics by status
        $voucher_stats = $tenant->vouchers()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Get hotspot statistics
        $hotspot_stats = $tenant->hotspots()
            ->selectRaw('is_active, COUNT(*) as count')
            ->groupBy('is_active')
            ->get()
            ->keyBy('is_active');

        return view('admin.dashboard.tenant-details', compact(
            'tenant', 
            'tenant_stats', 
            'voucher_stats', 
            'hotspot_stats'
        ));
    }

    /**
     * Show withdrawal requests
     */
    public function withdrawals()
    {
        $withdrawals = WithdrawalTransaction::with(['tenant', 'admin'])
            ->latest()
            ->paginate(20);

        return view('admin.dashboard.withdrawals', compact('withdrawals'));
    }

    /**
     * Approve withdrawal request
     */
    public function approveWithdrawal(Request $request, $id)
    {
        $withdrawal = WithdrawalTransaction::findOrFail($id);
        
        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'This withdrawal request has already been processed.');
        }

        $admin = Auth::guard('admin')->user();

        DB::beginTransaction();
        try {
            // Update withdrawal status
            $withdrawal->update([
                'status' => 'completed',
                'admin_id' => $admin->id,
                'admin_notes' => $request->admin_notes,
                'approved_at' => now(),
                'completed_at' => now(),
            ]);

            // Update tenant wallet balance
            $tenant = $withdrawal->tenant;
            $tenant->decrement('wallet_balance', $withdrawal->amount);

            DB::commit();

            // Send approval email to tenant
            try {
                $emailService = new \App\Services\EmailService();
                $emailService->sendWithdrawalApprovalEmail($tenant, $withdrawal);
            } catch (\Exception $emailException) {
                // Log email error but don't fail the transaction
                \Log::error('Failed to send withdrawal approval email', [
                    'tenant_id' => $tenant->id,
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $emailException->getMessage(),
                ]);
            }

            return back()->with('success', 'Withdrawal request approved and funds deducted from tenant wallet. Email notification sent.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to approve withdrawal: ' . $e->getMessage());
        }
    }

    /**
     * Reject withdrawal request
     */
    public function rejectWithdrawal(Request $request, $id)
    {
        $withdrawal = WithdrawalTransaction::findOrFail($id);
        
        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'This withdrawal request has already been processed.');
        }

        $admin = Auth::guard('admin')->user();

        $withdrawal->update([
            'status' => 'failed',
            'admin_id' => $admin->id,
            'admin_notes' => $request->admin_notes,
            'rejected_at' => now(),
            'failed_at' => now(),
        ]);

        // Send rejection email to tenant
        try {
            $emailService = new \App\Services\EmailService();
            $emailService->sendWithdrawalRejectionEmail($withdrawal->tenant, $withdrawal);
        } catch (\Exception $emailException) {
            // Log email error but don't fail the transaction
            \Log::error('Failed to send withdrawal rejection email', [
                'tenant_id' => $withdrawal->tenant->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $emailException->getMessage(),
            ]);
        }

        return back()->with('success', 'Withdrawal request rejected. Email notification sent.');
    }

    /**
     * Toggle tenant status
     */
    public function toggleTenantStatus($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => !$tenant->is_active]);

        $status = $tenant->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Tenant {$status} successfully.");
    }

    /**
     * Show transactions
     */
    public function transactions()
    {
        $query = Transaction::with(['tenant', 'hotspot', 'package', 'voucher'])->latest();
        if (request()->filled('q')) {
            $term = trim(request('q'));
            $query->where(function ($q) use ($term) {
                $q->where('phone_number', 'like', '%' . $term . '%');
            });
        }
        $transactions = $query->paginate(20)->appends(request()->only('q'));

        return view('admin.dashboard.transactions', compact('transactions'));
    }

    /**
     * Show admin profile/settings
     */
    public function profile()
    {
        $admin = Auth::guard('admin')->user();
        return view('admin.dashboard.profile', compact('admin'));
    }

    /**
     * Update admin profile
     */
    public function updateProfile(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:admins,email,' . $admin->id,
        ]);

        $admin->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Change admin password
     */
    public function changePassword(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Current password is required.',
            'new_password.required' => 'New password is required.',
            'new_password.min' => 'New password must be at least 8 characters.',
            'new_password.confirmed' => 'Password confirmation does not match.',
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $admin->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        // Update password
        $admin->update([
            'password' => $request->new_password,
        ]);

        return back()->with('success', 'Password changed successfully.');
    }

    /**
     * Permanently delete a tenant and all related data
     */
    public function deleteTenant(Request $request, $id)
    {
        $admin = Auth::guard('admin')->user();
        $tenant = Tenant::findOrFail($id);

        DB::beginTransaction();
        try {
            // Delete related notifications
            Notification::where('tenant_id', $tenant->id)->delete();

            // Delete transactions and withdrawal transactions
            Transaction::where('tenant_id', $tenant->id)->delete();
            WithdrawalTransaction::where('tenant_id', $tenant->id)->delete();

            // Delete vouchers
            Voucher::where('tenant_id', $tenant->id)->delete();

            // Delete packages belonging to tenant's hotspots
            $hotspotIds = Hotspot::where('tenant_id', $tenant->id)->pluck('id');
            if ($hotspotIds->isNotEmpty()) {
                Package::whereIn('hotspot_id', $hotspotIds)->delete();
            }

            // Delete hotspots
            Hotspot::where('tenant_id', $tenant->id)->delete();

            // If Tenant has subscriptionPlans relation, delete them
            if (method_exists($tenant, 'subscriptionPlans')) {
                $tenant->subscriptionPlans()->delete();
            }

            // Finally delete the tenant
            $tenant->delete();

            DB::commit();

            return redirect()->route('admin.tenants')->with('success', 'Tenant and all related data deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete tenant: ' . $e->getMessage());
        }
    }
}