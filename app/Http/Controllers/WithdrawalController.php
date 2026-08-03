<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalTransaction;
use App\Models\Tenant;
use App\Services\JpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class WithdrawalController extends Controller
{
    protected $jpesaService;

    public function __construct()
    {
        $this->jpesaService = new JpesaService();
    }

    /**
     * Show withdrawal form
     */
    public function showWithdrawalForm()
    {
        $tenant = Auth::user();
        
        return view('dashboard.withdrawal.form', compact('tenant'));
    }

    /**
     * Process withdrawal request
     */
    public function initiateWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100|max:1000000',
            'phone_number' => 'required|string|min:10|max:15',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = Auth::user();
        $amount = $request->amount;
        $phoneNumber = $request->phone_number;
        $description = $request->description ?? 'Wallet withdrawal';

        // Check if tenant has a pending withdrawal request
        if (WithdrawalTransaction::hasPendingWithdrawal($tenant->id)) {
            $pendingWithdrawal = WithdrawalTransaction::getPendingWithdrawal($tenant->id);
            return back()->with('error', 'You already have a pending withdrawal request (ID: ' . $pendingWithdrawal->withdrawal_id . '). Please wait for it to be processed before submitting a new request.')->withInput();
        }

        // Validate phone number format
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        if (!$this->isValidUgandaPhoneNumber($phoneNumber)) {
            return back()->with('error', 'Please enter a valid Uganda phone number (e.g., 0744744888, 0397373763)')->withInput();
        }

        // Check if withdrawal amount exceeds wallet balance
        if ($amount > $tenant->wallet_balance) {
            return back()->with('error', 'Insufficient wallet balance. Available balance: UGX ' . number_format($tenant->wallet_balance))->withInput();
        }

        // Check minimum withdrawal amount
        if ($amount < 100) {
            return back()->with('error', 'Minimum withdrawal amount is UGX 100')->withInput();
        }

        // Calculate withdrawal fee (2% of amount, minimum 50 UGX)
        $withdrawalFee = max(50, $amount * 0.02);
        $netAmount = $amount - $withdrawalFee;

        // Check if net amount is positive after fees
        if ($netAmount <= 0) {
            return back()->with('error', 'Withdrawal amount is too small to cover fees')->withInput();
        }

        try {
            DB::beginTransaction();

            // Create withdrawal transaction
            $withdrawal = WithdrawalTransaction::create([
                'tenant_id' => $tenant->id,
                'withdrawal_id' => WithdrawalTransaction::generateWithdrawalId(),
                'amount' => $amount,
                'fee' => $withdrawalFee,
                'net_amount' => $netAmount,
                'phone_number' => $phoneNumber,
                'currency' => 'UGX',
                'status' => 'pending',
                'description' => $description,
            ]);

            Log::info('Withdrawal transaction created', [
                'withdrawal_id' => $withdrawal->withdrawal_id,
                'tenant_id' => $tenant->id,
                'amount' => $amount,
                'net_amount' => $netAmount,
                'phone_number' => $phoneNumber
            ]);

            // Check if we're in development mode
            if (config('app.env') === 'local' && config('app.debug') === true) {
                Log::info('Development mode: Simulating withdrawal', [
                    'withdrawal_id' => $withdrawal->withdrawal_id
                ]);
                
                // Simulate successful withdrawal
                $this->simulateWithdrawal($withdrawal, $tenant);
                
                DB::commit();
                return redirect()->route('withdrawal.success', $withdrawal->withdrawal_id)
                    ->with('success', 'Withdrawal completed successfully! Check your phone for the confirmation.');
            }

            // Initiate real withdrawal via JPesa
            $callbackUrl = route('withdrawal.callback');
            $result = $this->jpesaService->initiateWithdrawal(
                $phoneNumber,
                $netAmount, // Use net amount for actual withdrawal
                $withdrawal->withdrawal_id,
                $description,
                $callbackUrl
            );

            if ($result['success']) {
                // Update withdrawal with JPesa details
                $withdrawal->update([
                    'status' => 'processing',
                    'processed_at' => now(),
                    'payment_details' => $result['data']
                ]);

                // Reserve the amount in wallet (don't deduct yet, wait for callback)
                $tenant->wallet_balance -= $amount;
                $tenant->save();

                DB::commit();

                Log::info('Withdrawal initiated successfully', [
                    'withdrawal_id' => $withdrawal->withdrawal_id,
                    'jpesa_tid' => $result['data']['jpesa_tid'] ?? null
                ]);

                return redirect()->route('withdrawal.pending', $withdrawal->withdrawal_id)
                    ->with('success', 'Withdrawal initiated successfully. Processing...');
            } else {
                DB::rollBack();
                
                Log::error('Withdrawal initiation failed', [
                    'withdrawal_id' => $withdrawal->withdrawal_id,
                    'error' => $result['message']
                ]);

                return back()->with('error', 'Withdrawal failed: ' . $result['message'])->withInput();
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Withdrawal initiation exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'An error occurred while processing your withdrawal. Please try again.')->withInput();
        }
    }

    /**
     * Show withdrawal pending page
     */
    public function pending($withdrawalId)
    {
        $withdrawal = WithdrawalTransaction::where('withdrawal_id', $withdrawalId)
            ->where('tenant_id', Auth::id())
            ->firstOrFail();

        return view('dashboard.withdrawal.pending', compact('withdrawal'));
    }

    /**
     * Show withdrawal success page
     */
    public function success($withdrawalId)
    {
        $withdrawal = WithdrawalTransaction::where('withdrawal_id', $withdrawalId)
            ->where('tenant_id', Auth::id())
            ->firstOrFail();

        return view('dashboard.withdrawal.success', compact('withdrawal'));
    }

    /**
     * Handle withdrawal callback from JPesa
     */
    public function handleCallback(Request $request)
    {
        Log::info('Withdrawal callback received', [
            'request_data' => $request->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            // Extract callback data
            $withdrawalId = $request->input('tx');
            $jpesaTid = $request->input('tid');
            $status = $request->input('api_status');
            $message = $request->input('msg');
            $memo = $request->input('memo');

            if (!$withdrawalId) {
                Log::error('Withdrawal callback: No withdrawal ID provided', [
                    'request_data' => $request->all()
                ]);
                return response('OK', 200);
            }

            // Find withdrawal transaction
            $withdrawal = WithdrawalTransaction::where('withdrawal_id', $withdrawalId)->first();

            if (!$withdrawal) {
                Log::error('Withdrawal callback: Withdrawal not found', [
                    'withdrawal_id' => $withdrawalId,
                    'request_data' => $request->all()
                ]);
                return response('OK', 200);
            }

            // Check if callback was already processed
            $existingCallback = $withdrawal->payment_details['callback_received_at'] ?? null;
            if ($existingCallback) {
                Log::warning('Withdrawal callback: Already processed', [
                    'withdrawal_id' => $withdrawalId,
                    'existing_callback_time' => $existingCallback
                ]);
                return response('OK', 200);
            }

            // Update withdrawal with callback data
            $updateData = [
                'payment_details' => array_merge($withdrawal->payment_details ?? [], [
                    'jpesa_callback_tid' => $jpesaTid,
                    'jpesa_callback_memo' => $memo,
                    'jpesa_callback_message' => $message,
                    'jpesa_callback_status' => $status,
                    'callback_received_at' => now()->toISOString(),
                    'callback_data' => $request->all()
                ])
            ];

            if ($status === 'success') {
                if ($withdrawal->status !== 'completed') {
                    $updateData['status'] = 'completed';
                    $updateData['completed_at'] = now();
                    
                    // Wallet balance was already deducted during initiation
                    // No need to deduct again, just confirm the withdrawal
                    
                    Log::info('Withdrawal completed via callback', [
                        'withdrawal_id' => $withdrawalId,
                        'jpesa_tid' => $jpesaTid,
                        'amount' => $withdrawal->amount,
                        'net_amount' => $withdrawal->net_amount,
                        'wallet_already_deducted' => true
                    ]);
                }
            } else {
                // Handle failed withdrawal
                $updateData['status'] = 'failed';
                $updateData['failed_at'] = now();
                
                // Return the reserved amount to wallet
                $tenant = $withdrawal->tenant;
                $tenant->wallet_balance += $withdrawal->amount;
                $tenant->save();
                
                Log::warning('Withdrawal failed via callback', [
                    'withdrawal_id' => $withdrawalId,
                    'jpesa_tid' => $jpesaTid,
                    'status' => $status,
                    'message' => $message,
                    'amount_returned_to_wallet' => $withdrawal->amount
                ]);
            }

            $withdrawal->update($updateData);

            Log::info('Withdrawal callback processed successfully', [
                'withdrawal_id' => $withdrawalId,
                'jpesa_tid' => $jpesaTid,
                'new_status' => $updateData['status'] ?? $withdrawal->status
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Withdrawal callback handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response('ERROR', 500);
        }
    }

    /**
     * Check withdrawal status
     */
    public function checkStatus($withdrawalId)
    {
        try {
            $withdrawal = WithdrawalTransaction::where('withdrawal_id', $withdrawalId)
                ->where('tenant_id', Auth::id())
                ->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => $withdrawal->status,
                'message' => $this->getStatusMessage($withdrawal->status),
                'withdrawal_id' => $withdrawal->withdrawal_id,
                'amount' => $withdrawal->amount,
                'net_amount' => $withdrawal->net_amount,
                'completed_at' => $withdrawal->completed_at,
                'failed_at' => $withdrawal->failed_at
            ]);

        } catch (\Exception $e) {
            Log::error('Withdrawal status check failed', [
                'withdrawal_id' => $withdrawalId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking withdrawal status'
            ], 500);
        }
    }

    /**
     * Get withdrawal history
     */
    public function history()
    {
        $withdrawals = WithdrawalTransaction::where('tenant_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('dashboard.withdrawal.history', compact('withdrawals'));
    }

    /**
     * Format phone number to international format
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
        else if (!strpos($phoneNumber, '256') === 0) {
            $phoneNumber = '256' . $phoneNumber;
        }
        
        return $phoneNumber;
    }

    /**
     * Validate Uganda phone number
     */
    private function isValidUgandaPhoneNumber($phoneNumber)
    {
        // Uganda phone numbers should be 12 digits (256 + 9 digits)
        return preg_match('/^256[0-9]{9}$/', $phoneNumber);
    }

    /**
     * Get status message for display
     */
    private function getStatusMessage($status)
    {
        switch ($status) {
            case 'pending':
                return 'Withdrawal is pending';
            case 'processing':
                return 'Withdrawal is being processed';
            case 'completed':
                return 'Withdrawal completed successfully';
            case 'failed':
                return 'Withdrawal failed';
            case 'cancelled':
                return 'Withdrawal was cancelled';
            default:
                return 'Withdrawal status unknown';
        }
    }

    /**
     * Simulate withdrawal for development
     */
    private function simulateWithdrawal($withdrawal, $tenant)
    {
        // Simulate successful withdrawal
        $withdrawal->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payment_details' => [
                'jpesa_tid' => 'SIM_' . uniqid(),
                'jpesa_memo' => 'SIM_MEMO_' . rand(100, 999),
                'jpesa_message' => '[[S000103]] MM transaction completed (simulated)',
                'simulated' => true
            ]
        ]);

        // Deduct amount from wallet
        $tenant->wallet_balance -= $withdrawal->amount;
        $tenant->save();

        Log::info('Simulated withdrawal completed', [
            'withdrawal_id' => $withdrawal->withdrawal_id,
            'amount_deducted' => $withdrawal->amount,
            'new_balance' => $tenant->wallet_balance
        ]);
    }
}