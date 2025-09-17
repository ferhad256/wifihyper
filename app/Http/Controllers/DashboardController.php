<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Hotspot;
use App\Models\Voucher;
use App\Models\Notification;
use App\Models\WithdrawalTransaction;
use App\Services\JpesaService;
use App\Services\VoucherAvailabilityService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $jpesaService;

    public function __construct(JpesaService $jpesaService)
    {
        $this->jpesaService = $jpesaService;
    }

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

        // Calculate day, week, month, and year sales amounts
        $sales_summary = [
            'today' => [
                'amount' => $tenant->transactions()
                    ->where('status', 'completed')
                    ->whereDate('created_at', today())
                    ->sum('amount'),
                'start_date' => today()->format('M d, Y')
            ],
            'this_week' => [
                'amount' => $tenant->transactions()
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->sum('amount'),
                'start_date' => now()->subDays(7)->format('M d, Y')
            ],
            'this_month' => [
                'amount' => $tenant->transactions()
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('amount'),
                'start_date' => now()->startOfMonth()->format('M d, Y')
            ],
            'this_year' => [
                'amount' => $tenant->transactions()
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->startOfYear())
                    ->sum('amount'),
                'start_date' => now()->startOfYear()->format('M d, Y')
            ],
        ];

        return view('dashboard.index', compact('tenant', 'stats', 'recent_transactions', 'filled_sales_data', 'sales_summary'));
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

        // Get withdrawal requests for this tenant
        $withdrawal_requests = $tenant->withdrawalTransactions()
            ->latest()
            ->take(10)
            ->get();

        // Calculate daily transaction statistics
        $daily_stats = [
            'completed' => $tenant->transactions()
                ->where('status', 'completed')
                ->whereDate('created_at', today())
                ->count(),
            'pending' => $tenant->transactions()
                ->where('status', 'pending')
                ->whereDate('created_at', today())
                ->count(),
            'failed' => $tenant->transactions()
                ->where('status', 'failed')
                ->whereDate('created_at', today())
                ->count(),
            'total_amount' => $tenant->transactions()
                ->where('status', 'completed')
                ->whereDate('created_at', today())
                ->sum('amount'),
        ];

        return view('dashboard.billing', compact('tenant', 'transactions', 'daily_stats', 'withdrawal_requests'));
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
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $request->validate([
            'amount' => 'required|numeric|min:5000|max:' . $tenant->wallet_balance,
            'phone_number' => 'required|string|min:10|max:15',
        ], [
            'amount.min' => 'Minimum withdrawal amount is UGX 5,000',
            'amount.max' => 'Withdrawal amount cannot exceed your wallet balance',
            'phone_number.required' => 'Phone number is required for withdrawal',
            'phone_number.min' => 'Phone number must be at least 10 digits',
            'phone_number.max' => 'Phone number must not exceed 15 digits',
        ]);
        $amount = $request->amount;
        $phoneNumber = $this->formatPhoneNumber($request->phone_number);

        // Validate phone number format
        if (!$this->isValidUgandaPhoneNumber($phoneNumber)) {
            return back()->with('error', 'Please enter a valid Uganda phone number.')->withInput();
        }

        try {
            // Calculate 3% withdrawal fee
            $withdrawalFee = $amount * 0.03; // 3% fee
            $netAmount = $amount - $withdrawalFee;

            // Create withdrawal request (no wallet deduction yet - admin will approve)
            $withdrawal = WithdrawalTransaction::create([
                'tenant_id' => $tenant->id,
                'withdrawal_id' => 'WD_' . time() . '_' . rand(1000, 9999),
                'amount' => $amount,
                'fee' => $withdrawalFee,
                'net_amount' => $netAmount,
                'phone_number' => $phoneNumber,
                'currency' => 'UGX',
                'status' => 'pending',
                'description' => $request->description ?? 'Wallet withdrawal request',
            ]);

            Log::info('Withdrawal request created', [
                'withdrawal_id' => $withdrawal->withdrawal_id,
                'tenant_id' => $tenant->id,
                'amount' => $amount,
                'phone_number' => $phoneNumber
            ]);

            return redirect()->back()->with('success', 'Withdrawal request submitted successfully. An admin will review and process your request within 24 hours.');

        } catch (\Exception $e) {
            Log::error('Withdrawal request failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return redirect()->back()->with('error', 'Failed to submit withdrawal request. Please try again.');
        }
    }

    /**
     * Format phone number for payment processing (256xxxxxxxxx format)
     */
    private function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If number starts with 0, remove it and add 256
        if (strpos($phoneNumber, '0') === 0) {
            $phoneNumber = '256' . substr($phoneNumber, 1);
        }
        // If number doesn't start with 256, add it
        else if (strpos($phoneNumber, '256') !== 0) {
            $phoneNumber = '256' . $phoneNumber;
        }
        
        return $phoneNumber;
    }

    /**
     * Validate Uganda phone number format
     */
    private function isValidUgandaPhoneNumber($phoneNumber)
    {
        // Uganda phone numbers should be 12 digits (256 + 9 digits)
        return preg_match('/^256[0-9]{9}$/', $phoneNumber);
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
