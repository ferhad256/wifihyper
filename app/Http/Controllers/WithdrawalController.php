<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalTransaction;
use App\Services\JpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller
{
    protected $jpesaService;

    public function __construct()
    {
        $this->jpesaService = new JpesaService();
    }

    /*
    |---------------------------------------------------------------------
    | Only the JPesa callback lives here.
    |
    | The tenant-facing withdrawal screens this controller used to serve
    | were dead: they resolved Auth::user() on the web guard, whose
    | provider is App\Models\User, so it was always null for a tenant and
    | initiateWithdrawal() fatalled on $tenant->id. The views they
    | rendered (dashboard/withdrawal/*) were never created either.
    |
    | The live withdrawal request path is DashboardController::withdraw().
    | Do not revive the old one: it charged a different fee (2% with a 50
    | UGX floor, against 5%) and never checked that the destination number
    | matched the tenant's registered phone.
    |---------------------------------------------------------------------
    */
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
}
