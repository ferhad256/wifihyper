<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Hotspot;
use App\Models\Voucher;
use App\Models\Notification;
use App\Services\YoPaymentsService;
use App\Services\VoucherAvailabilityService;
use App\Services\NotificationService;
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

        // Check for low voucher notifications on login
        $notificationService = new NotificationService();
        $notificationService->checkLowVoucherNotifications($tenant);
        $notificationService->checkNoVoucherNotifications($tenant);

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

        return view('dashboard.index', compact('tenant', 'stats', 'recent_transactions', 'filled_sales_data'));
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
                "WIFIHYPER Withdrawal - " . $tenant->business_name,
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

        $notificationService = new NotificationService();
        $notificationId = $request->input('notification_id');
        
        if ($notificationService->markAsRead($tenant, $notificationId)) {
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

        $notificationService = new NotificationService();
        $count = $notificationService->markAllAsRead($tenant);

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

        $notificationService = new NotificationService();
        $count = $notificationService->getUnreadCount($tenant);

        return response()->json(['count' => $count]);
    }

    /**
     * Get notifications for dropdown
     */
    public function getNotifications()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notificationService = new NotificationService();
        $notifications = $notificationService->getNotificationsForDropdown($tenant);

        return response()->json(['notifications' => $notifications]);
    }

    /**
     * Export transactions as CSV
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

        $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, ['Date', 'Transaction ID', 'Hotspot', 'Package', 'Voucher Code', 'Amount', 'Fee', 'Net Amount', 'Phone Number', 'Status']);
            
            // Add data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->transaction_id,
                    $transaction->hotspot ? $transaction->hotspot->name : 'N/A',
                    $transaction->package ? $transaction->package->name : 'N/A',
                    $transaction->voucher ? $transaction->voucher->code : 'N/A',
                    $transaction->amount,
                    $transaction->transaction_fee,
                    $transaction->net_amount,
                    $transaction->phone_number ?? 'N/A',
                    $transaction->status,
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
