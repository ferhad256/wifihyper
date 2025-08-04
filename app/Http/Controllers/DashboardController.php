<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Hotspot;
use App\Models\Voucher;
use App\Models\Notification;
use App\Services\YoPaymentsService;
use App\Services\VoucherAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Show the main dashboard
     */
    public function index()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        // Get dashboard statistics
        $stats = [
            'total_sales' => $tenant->total_sales,
            'today_sales' => $tenant->today_sales,
            'unused_vouchers' => $tenant->unused_vouchers_count,
            'total_hotspots' => $tenant->hotspots()->count(),
            'total_transactions' => $tenant->transactions()->count(),
        ];

        // Get recent transactions (last 10)
        $recent_transactions = $tenant->transactions()
            ->with(['hotspot', 'package', 'voucher'])
            ->latest()
            ->take(10)
            ->get();

        // Get unread notifications
        $unread_notifications = $tenant->notifications()
            ->where('status', 'unread')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Get sales chart data (last 30 days with better formatting)
        $sales_data = $tenant->transactions()
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total' => (float) $item->total,
                    'count' => $item->count,
                    'formatted_date' => date('M d', strtotime($item->date))
                ];
            });

        // Fill in missing dates with zero values
        $filled_sales_data = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $existing = $sales_data->where('date', $date)->first();
            
            if ($existing) {
                $filled_sales_data->push($existing);
            } else {
                $filled_sales_data->push([
                    'date' => $date,
                    'total' => 0,
                    'count' => 0,
                    'formatted_date' => now()->subDays($i)->format('M d')
                ]);
            }
        }

        return view('dashboard.index', compact('tenant', 'stats', 'recent_transactions', 'filled_sales_data', 'unread_notifications'));
    }

    /**
     * Show billing/transactions page
     */
    public function billing()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $transactions = $tenant->transactions()
            ->with(['hotspot', 'package', 'voucher'])
            ->latest()
            ->paginate(20);

        return view('dashboard.billing', compact('tenant', 'transactions'));
    }

    /**
     * Show hotspots management page
     */
    public function hotspots()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $hotspots = $tenant->hotspots()->with('packages')->get();

        return view('dashboard.hotspots', compact('tenant', 'hotspots'));
    }

    /**
     * Show settings page
     */
    public function settings()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        return view('dashboard.settings', compact('tenant'));
    }

    /**
     * Show profile page
     */
    public function profile()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        return view('dashboard.profile', compact('tenant'));
    }

    /**
     * Handle withdraw request
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000|max:' . auth()->user()->wallet_balance,
            'phone_number' => 'required|string|min:10|max:15',
        ], [
            'amount.min' => 'Minimum withdrawal amount is UGX 10,000',
            'amount.max' => 'Withdrawal amount cannot exceed your wallet balance',
            'phone_number.required' => 'Phone number is required for withdrawal',
            'phone_number.min' => 'Phone number must be at least 10 digits',
            'phone_number.max' => 'Phone number must not exceed 15 digits',
        ]);

        $tenant = auth()->user();
        $amount = $request->amount;
        $phoneNumber = $request->phone_number;

        try {
            DB::beginTransaction();

            // Deduct amount from wallet balance
            $tenant->wallet_balance -= $amount;
            $tenant->save();

            // Create withdrawal transaction record
            $transaction = Transaction::create([
                'tenant_id' => $tenant->id,
                'transaction_id' => 'WITHDRAW_' . time() . '_' . rand(1000, 9999),
                'amount' => -$amount, // Negative amount for withdrawal
                'currency' => 'UGX',
                'status' => 'pending',
                'payment_method' => 'withdrawal',
                'phone_number' => $phoneNumber,
                'payment_details' => [
                    'withdrawal_requested_at' => now(),
                    'withdrawal_phone' => $phoneNumber,
                ],
            ]);

            // Initialize Yo Payments withdrawal
            $yoPayments = new YoPaymentsService();
            $withdrawalResponse = $yoPayments->initiateWithdrawal(
                $amount,
                $phoneNumber,
                "WiFi SaaS Withdrawal - " . $tenant->business_name,
                $transaction->transaction_id
            );

            if ($withdrawalResponse['success']) {
                // Update transaction with Yo Payments details
                $transaction->update([
                    'payment_details' => array_merge($transaction->payment_details, [
                        'yo_payments_response' => $withdrawalResponse['data'],
                        'yo_transaction_reference' => $withdrawalResponse['transaction_reference'] ?? null,
                        'withdrawal_initiated_at' => now(),
                    ]),
                ]);

                DB::commit();

                return redirect()->back()->with('success', 'Withdrawal request submitted successfully. You will receive the funds shortly.');
            } else {
                // If Yo Payments fails, revert the wallet balance
                $tenant->wallet_balance += $amount;
                $tenant->save();

                // Update transaction status to failed
                $transaction->update([
                    'status' => 'failed',
                    'payment_details' => array_merge($transaction->payment_details, [
                        'yo_payments_error' => $withdrawalResponse['message'],
                        'withdrawal_failed_at' => now(),
                    ]),
                ]);

                DB::commit();

                return redirect()->back()->with('error', 'Withdrawal failed: ' . $withdrawalResponse['message']);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Withdrawal Error', [
                'tenant_id' => $tenant->id,
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Withdrawal failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notificationId = $request->input('notification_id');
        
        $notification = $tenant->notifications()
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Notification not found'], 404);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $count = $tenant->notifications()
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Get unread notifications count for AJAX requests
     */
    public function getUnreadNotificationsCount()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $count = $tenant->notifications()
            ->where('status', 'unread')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Export transactions as PDF
     */
    public function exportTransactions()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $transactions = $tenant->transactions()
            ->with(['hotspot', 'package', 'voucher'])
            ->latest()
            ->get();

        $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.html';
        
        // Generate HTML that can be printed as PDF
        $html = view('dashboard.transactions-pdf', compact('transactions', 'tenant'))->render();
        
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
