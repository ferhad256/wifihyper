<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\Voucher;
use App\Models\Notification;
use App\Models\SubscriptionPlan;
use App\Services\YoPaymentsService;
use App\Services\UgSmsService;
use App\Services\VoucherAvailabilityService;
use App\Services\TransactionFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $yoPayments;
    protected $voucherAvailabilityService;
    protected $transactionFeeService;

    public function __construct()
    {
        $this->yoPayments = new YoPaymentsService();
        $this->voucherAvailabilityService = new VoucherAvailabilityService();
        $this->transactionFeeService = new TransactionFeeService();
    }

    /**
     * Show payment form for a package
     */
    public function showPaymentForm(Request $request, $hotspotId)
    {
        $package = Package::findOrFail($request->package_id);
        
        return view('portal.payment', compact('package'));
    }

    /**
     * Initiate payment (called from portal form)
     */
    public function initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'phone_number' => 'required|string',
            'hotspot_id' => 'required|exists:hotspots,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $package = Package::findOrFail($request->package_id);
        $hotspot = $package->hotspot;
        $tenant = $hotspot->tenant;

        // Enhanced voucher availability check with notification
        $availability = $this->voucherAvailabilityService->checkVoucherAvailability($tenant, $package);
        
        if (!$availability['has_vouchers']) {
            // Create notification for admin about voucher shortage
            $this->createVoucherShortageNotification($tenant, $package);
            
            return back()->with('error', 'No vouchers available for the ' . $package->name . ' package. Please contact the hotspot owner to upload more vouchers.')->withInput();
        }

        // Find an available voucher for the specific package with enhanced validation
        $voucher = $tenant->vouchers()
            ->where('status', 'unused')
            ->where('package_id', $package->id)
            ->whereNull('used_at')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->lockForUpdate() // Prevent race conditions
            ->first();

        if (!$voucher) {
            // Create notification for admin about voucher shortage
            $this->createVoucherShortageNotification($tenant, $package);
            
            return back()->with('error', 'No vouchers available for the ' . $package->name . ' package. Please contact the hotspot owner to upload more vouchers.')->withInput();
        }

        // Calculate transaction fees
        $feeCalculation = $this->transactionFeeService->calculateFee($package->price);
        
        // Create transaction
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'hotspot_id' => $hotspot->id,
            'package_id' => $package->id,
            'voucher_id' => $voucher->id,
            'transaction_id' => 'TXN_' . time() . '_' . rand(1000, 9999),
            'amount' => $feeCalculation['amount'],
            'transaction_fee' => $feeCalculation['transaction_fee'],
            'net_amount' => $feeCalculation['net_amount'],
            'fee_percentage' => $feeCalculation['fee_percentage'],
            'currency' => 'UGX',
            'status' => 'pending',
            'phone_number' => $request->phone_number,
        ]);

        // Process payment via Yo Payments
        $result = $this->yoPayments->initiatePayment($transaction, $request->phone_number);

        if ($result['success']) {
            // Check if this is a simulated payment (development mode)
            if (config('app.env') === 'local' && config('app.debug') === true) {
                // For simulated payments, redirect directly to success
                session(['last_transaction_id' => $transaction->transaction_id]);
                return redirect()->route('payment.success')->with('success', 'Payment completed successfully! Check your phone for the WiFi voucher code.');
            } else {
                // For real payments, redirect to pending page
                return redirect()->route('payment.pending', $transaction->transaction_id);
            }
        } else {
            return back()->with('error', $result['message'])->withInput();
        }
    }

    /**
     * Process payment
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'phone_number' => 'required|string',
            'hotspot_id' => 'required|exists:hotspots,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $package = Package::findOrFail($request->package_id);
        $hotspot = $package->hotspot;
        $tenant = $hotspot->tenant;

        // Enhanced voucher availability check with notification
        $availability = $this->voucherAvailabilityService->checkVoucherAvailability($tenant, $package);
        
        if (!$availability['has_vouchers']) {
            // Create notification for admin about voucher shortage
            $this->createVoucherShortageNotification($tenant, $package);
            
            return back()->with('error', 'No vouchers available for the ' . $package->name . ' package. Please contact the hotspot owner to upload more vouchers.')->withInput();
        }

        // Find an available voucher for the specific package with enhanced validation
        $voucher = $tenant->vouchers()
            ->where('status', 'unused')
            ->where('package_id', $package->id)
            ->whereNull('used_at')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->lockForUpdate() // Prevent race conditions
            ->first();

        if (!$voucher) {
            // Create notification for admin about voucher shortage
            $this->createVoucherShortageNotification($tenant, $package);
            
            return back()->with('error', 'No vouchers available for the ' . $package->name . ' package. Please contact the hotspot owner to upload more vouchers.')->withInput();
        }

        // Calculate transaction fees
        $feeCalculation = $this->transactionFeeService->calculateFee($package->price);
        
        // Create transaction
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'hotspot_id' => $hotspot->id,
            'package_id' => $package->id,
            'voucher_id' => $voucher->id,
            'transaction_id' => YoPaymentsService::generateTransactionId(),
            'amount' => $feeCalculation['amount'],
            'transaction_fee' => $feeCalculation['transaction_fee'],
            'net_amount' => $feeCalculation['net_amount'],
            'fee_percentage' => $feeCalculation['fee_percentage'],
            'currency' => 'UGX',
            'status' => 'pending',
            'phone_number' => $request->phone_number,
        ]);

        // Process payment via Yo Payments
        $result = $this->yoPayments->initiatePayment($transaction, $request->phone_number);

        if ($result['success']) {
            // Redirect to payment URL
            return redirect($result['data']['payment_url'] ?? route('payment.pending', $transaction->transaction_id));
        } else {
            return back()->with('error', $result['message'])->withInput();
        }
    }

    /**
     * Handle payment callback
     */
    public function callback(Request $request)
    {
        $result = $this->yoPayments->processCallback($request->all());
        
        if ($result['success']) {
            // Update wallet balance and send SMS when payment is successful
            $this->updateWalletBalance($result['transaction_id']);
            $this->sendVoucherSms($result['transaction_id']);
            
            // Store transaction ID in session for success page
            session(['last_transaction_id' => $result['transaction_id']]);
        }
        
        return response()->json(['status' => 'success']);
    }

    /**
     * Handle failed payment notification from Yo Payments
     */
    public function failed(Request $request)
    {
        try {
            Log::info('Yo Payments Failure Notification', [
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Parse the failure notification data
            $transactionId = $request->input('TransactionReference');
            $failureReason = $request->input('FailureReason', 'Unknown failure');
            $amount = $request->input('Amount');
            $phoneNumber = $request->input('PhoneNumber');

            // Find the transaction
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            
            if (!$transaction) {
                Log::error('Yo Payments Failure: Transaction not found', ['transaction_id' => $transactionId]);
                return response('Transaction not found', 404);
            }

            // Update transaction status to failed
            $transaction->update([
                'status' => 'failed',
                'payment_details' => array_merge($transaction->payment_details ?? [], [
                    'failure_reason' => $failureReason,
                    'failure_notification_received_at' => now(),
                    'failure_amount' => $amount,
                    'failure_phone' => $phoneNumber,
                ]),
            ]);

            Log::info('Yo Payments Failure: Transaction updated', [
                'transaction_id' => $transactionId,
                'status' => 'failed',
                'reason' => $failureReason,
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Yo Payments Failure Notification Error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response('Error processing failure notification', 500);
        }
    }

    /**
     * Show payment success page
     */
    public function success(Request $request)
    {
        // Get the latest completed transaction for this session
        $transactionId = session('last_transaction_id');
        $transaction = null;
        $hotspotName = 'default';
        
        if ($transactionId) {
            $transaction = Transaction::where('transaction_id', $transactionId)
                ->where('status', 'completed')
                ->with(['voucher', 'package.hotspot'])
                ->first();
                
            if ($transaction && $transaction->package && $transaction->package->hotspot) {
                $hotspotName = $transaction->package->hotspot->name;
            }
        }
        
        return view('portal.success', compact('transaction', 'hotspotName'));
    }

    /**
     * Show payment pending page
     */
    public function pending($transactionId)
    {
        $transaction = Transaction::where('transaction_id', $transactionId)->firstOrFail();
        
        return view('portal.pending', compact('transaction'));
    }

    /**
     * Check payment status
     */
    public function checkStatus($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Check status using Yo Payments API
            $yoPaymentsService = new \App\Services\YoPaymentsService();
            $statusResult = $yoPaymentsService->verifyPayment($transactionId);

            if ($statusResult['success']) {
                // Update transaction with latest status
                $transaction->update([
                    'status' => $statusResult['status'],
                    'payment_details' => array_merge($transaction->payment_details ?? [], [
                        'last_status_check' => now(),
                        'status_check_result' => $statusResult['data'],
                        'transaction_details' => $statusResult['transaction_details'] ?? [],
                    ]),
                ]);

                // If payment is completed, update wallet balance and send SMS
                if ($statusResult['status'] === 'completed') {
                    $this->updateWalletBalance($transactionId);
                    $this->sendVoucherSms($transactionId);
                    
                    // Store transaction ID in session for success page
                    session(['last_transaction_id' => $transactionId]);
                }

                return response()->json([
                    'success' => true,
                    'status' => $statusResult['status'],
                    'is_pending' => $statusResult['is_pending'] ?? false,
                    'transaction_details' => $statusResult['transaction_details'] ?? [],
                    'message' => $statusResult['message'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $statusResult['message'],
            ], 400);

        } catch (\Exception $e) {
            Log::error('Payment Status Check Error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manual voucher redemption
     */
    public function redeemVoucher(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'voucher_code' => 'required|string',
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $voucher = Voucher::where('code', $request->voucher_code)
            ->where('status', 'unused')
            ->first();

        if (!$voucher) {
            return back()->with('error', 'Invalid or already used voucher code.')->withInput();
        }

        try {
            DB::beginTransaction();

            // Mark voucher as used
            $voucher->update([
                'status' => 'used',
                'used_at' => now(),
                'phone_number' => $request->phone_number,
            ]);

            // Create manual transaction
            $transaction = Transaction::create([
                'tenant_id' => $voucher->tenant_id,
                'package_id' => $voucher->package_id,
                'voucher_id' => $voucher->id,
                'transaction_id' => 'MANUAL_' . time(),
                'amount' => $voucher->package ? $voucher->package->price : 0,
                'currency' => 'UGX',
                'status' => 'completed',
                'phone_number' => $request->phone_number,
                'paid_at' => now(),
            ]);

            // Update tenant wallet balance
            $tenant = $voucher->tenant;
            $tenant->wallet_balance += $transaction->amount;
            $tenant->save();

            // Send SMS
            $smsService = new UgSmsService();
            $smsService->sendVoucherCode($request->phone_number, $voucher->code, $voucher->package);

            DB::commit();

            return back()->with('success', 'Voucher redeemed successfully! Check your phone for the WiFi code.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to redeem voucher.')->withInput();
        }
    }

    /**
     * Test payment (for development)
     */
    public function testPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'phone_number' => 'required|string',
            'hotspot_id' => 'required|exists:hotspots,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $package = Package::findOrFail($request->package_id);
        $hotspot = $package->hotspot;
        $tenant = $hotspot->tenant;

        // Find an available voucher for the specific package with enhanced validation
        $voucher = $tenant->vouchers()
            ->where('status', 'unused')
            ->where('package_id', $package->id)
            ->whereNull('used_at')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->lockForUpdate() // Prevent race conditions
            ->first();

        if (!$voucher) {
            return back()->with('error', 'No vouchers available for the ' . $package->name . ' package. Please contact the hotspot owner to upload more vouchers.')->withInput();
        }

        try {
            DB::beginTransaction();

            // Create test transaction
            $transaction = Transaction::create([
                'tenant_id' => $tenant->id,
                'hotspot_id' => $hotspot->id,
                'package_id' => $package->id,
                'voucher_id' => $voucher->id,
                'transaction_id' => 'TEST_' . time(),
                'amount' => $package->price,
                'currency' => 'UGX',
                'status' => 'completed',
                'phone_number' => $request->phone_number,
                'paid_at' => now(),
            ]);

            // Mark voucher as used
            $voucher->update([
                'status' => 'used',
                'used_at' => now(),
                'phone_number' => $request->phone_number,
            ]);

            // Update tenant wallet balance
            $tenant->wallet_balance += $transaction->amount;
            $tenant->save();

            // Send SMS with voucher code
            $smsService = new UgSmsService();
            $smsResult = $smsService->sendVoucherCode($request->phone_number, $voucher->code, $package);

            DB::commit();

            // Store transaction ID in session for success page
            session(['last_transaction_id' => $transaction->transaction_id]);

            if ($smsResult['success']) {
                return back()->with('success', 'Test payment successful! Voucher code sent to ' . $request->phone_number);
            } else {
                return back()->with('warning', 'Payment successful but SMS failed: ' . $smsResult['message']);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Test payment failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update wallet balance when payment is successful
     */
    private function updateWalletBalance($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if ($transaction && $transaction->status === 'pending') {
                DB::beginTransaction();
                
                // Update transaction status
                $transaction->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);
                
                // Update tenant wallet balance with net amount (after fees)
                $tenant = $transaction->tenant;
                $tenant->wallet_balance += $transaction->net_amount;
                $tenant->save();
                
                // Mark voucher as used with enhanced validation
                if ($transaction->voucher) {
                    $voucher = $transaction->voucher;
                    
                    // Double-check voucher is still available
                    if ($voucher->status === 'unused' && !$voucher->used_at) {
                        $voucher->update([
                            'status' => 'used',
                            'used_at' => now(),
                            'phone_number' => $transaction->phone_number,
                        ]);
                        
                        Log::info('Voucher marked as used', [
                            'transaction_id' => $transactionId,
                            'voucher_id' => $voucher->id,
                            'voucher_code' => $voucher->code,
                            'package_name' => $transaction->package->name ?? 'Unknown',
                            'phone_number' => $transaction->phone_number,
                        ]);
                    } else {
                        Log::warning('Voucher already used or invalid', [
                            'transaction_id' => $transactionId,
                            'voucher_id' => $voucher->id,
                            'voucher_status' => $voucher->status,
                            'voucher_used_at' => $voucher->used_at,
                        ]);
                    }
                }
                
                DB::commit();
                
                Log::info('Wallet balance updated successfully', [
                    'transaction_id' => $transactionId,
                    'amount' => $transaction->amount,
                    'new_balance' => $tenant->wallet_balance,
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update wallet balance', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send voucher code via SMS when payment is successful
     */
    private function sendVoucherSms($transactionId)
    {
        try {
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            if ($transaction && $transaction->status === 'completed' && $transaction->voucher) {
                $voucher = $transaction->voucher;
                
                // Verify voucher is valid and matches the package
                if ($voucher->status === 'used' && $voucher->package_id === $transaction->package_id) {
                    $smsService = new UgSmsService();
                    $smsResult = $smsService->sendVoucherCode(
                        $transaction->phone_number,
                        $voucher->code,
                        $transaction->package
                    );

                    if ($smsResult['success']) {
                        Log::info('Voucher SMS sent successfully', [
                            'transaction_id' => $transactionId,
                            'voucher_code' => $voucher->code,
                            'package_name' => $transaction->package->name ?? 'Unknown',
                            'phone_number' => $transaction->phone_number,
                        ]);
                    } else {
                        Log::error('Failed to send voucher SMS', [
                            'transaction_id' => $transactionId,
                            'voucher_code' => $voucher->code,
                            'error' => $smsResult['message'],
                        ]);
                    }
                } else {
                    Log::warning('Invalid voucher for SMS sending', [
                        'transaction_id' => $transactionId,
                        'voucher_id' => $voucher->id,
                        'voucher_status' => $voucher->status,
                        'voucher_package_id' => $voucher->package_id,
                        'transaction_package_id' => $transaction->package_id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send voucher SMS', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create notification for voucher shortage
     */
    private function createVoucherShortageNotification(Tenant $tenant, Package $package): void
    {
        // Check if notification already exists for this package
        $existingNotification = $tenant->notifications()
            ->where('type', 'voucher_shortage')
            ->where('data->package_id', $package->id)
            ->where('status', 'unread')
            ->first();

        if (!$existingNotification) {
            Notification::create([
                'tenant_id' => $tenant->id,
                'type' => 'voucher_shortage',
                'title' => 'Voucher Shortage Alert',
                'message' => "No vouchers available for package '{$package->name}' in hotspot '{$package->hotspot->name}'. Customer payment was rejected.",
                'data' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'hotspot_id' => $package->hotspot->id,
                    'hotspot_name' => $package->hotspot->name,
                    'timestamp' => now()->toISOString(),
                ],
            ]);

            Log::warning('Voucher shortage notification created', [
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'hotspot_id' => $package->hotspot->id,
                'hotspot_name' => $package->hotspot->name,
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Show subscription payment form
     */
    public function showSubscriptionPayment(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $planId = $request->get('plan_id');
        $plan = SubscriptionPlan::findOrFail($planId);
        
        if ($plan->slug === 'starter') {
            return back()->with('error', 'Starter plan is free and does not require payment.');
        }

        return view('dashboard.subscription.payment', compact('tenant', 'plan'));
    }

    /**
     * Initiate subscription payment
     */
    public function initiateSubscriptionPayment(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        
        if ($plan->slug === 'starter') {
            return back()->with('error', 'Starter plan is free and does not require payment.');
        }

        if ($plan->slug === 'enterprise') {
            return back()->with('error', 'Enterprise plan requires contacting sales team.');
        }

        // Create subscription transaction
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'transaction_id' => 'SUBS_' . time() . '_' . rand(1000, 9999),
            'amount' => $plan->monthly_price,
            'phone_number' => $request->phone_number,
            'status' => 'pending',
            'type' => 'subscription',
            'data' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
                'subscription_period' => 'monthly',
            ],
        ]);

        // Initiate payment with Yo! Payments
        $paymentData = [
            'amount' => $plan->monthly_price,
            'phone_number' => $request->phone_number,
            'transaction_id' => $transaction->transaction_id,
            'description' => "Subscription payment for {$plan->name} plan",
        ];

        try {
            $response = $this->yoPayments->initiatePayment($paymentData);
            
            if ($response['success']) {
                return redirect()->away($response['payment_url']);
            } else {
                $transaction->update(['status' => 'failed']);
                return back()->with('error', 'Failed to initiate payment: ' . $response['message']);
            }
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            Log::error('Subscription payment initiation failed', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to initiate payment. Please try again.');
        }
    }

    /**
     * Handle subscription payment callback
     */
    public function subscriptionCallback(Request $request)
    {
        $transactionId = $request->get('transaction_id');
        $transaction = Transaction::where('transaction_id', $transactionId)
            ->where('type', 'subscription')
            ->first();

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $tenant = $transaction->tenant;
        $planData = $transaction->data;
        $plan = SubscriptionPlan::find($planData['plan_id']);

        if (!$plan) {
            return response()->json(['error' => 'Plan not found'], 404);
        }

        // Verify payment with Yo! Payments
        $verificationData = [
            'transaction_id' => $transactionId,
        ];

        try {
            $response = $this->yoPayments->verifyPayment($verificationData);
            
            if ($response['success'] && $response['status'] === 'successful') {
                // Update transaction status
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Update tenant subscription
                $tenant->update([
                    'subscription_plan_id' => $plan->id,
                    'subscription_expires_at' => now()->addMonth(),
                ]);

                // Create success notification
                $tenant->notifications()->create([
                    'title' => 'Subscription Upgraded',
                    'message' => "Successfully upgraded to {$plan->name} plan. Your subscription expires on " . now()->addMonth()->format('M d, Y'),
                    'type' => 'subscription_upgrade',
                    'data' => [
                        'plan_name' => $plan->name,
                        'expires_at' => now()->addMonth()->toISOString(),
                    ],
                    'is_read' => false,
                ]);

                return response()->json(['success' => true, 'message' => 'Subscription upgraded successfully']);
            } else {
                $transaction->update(['status' => 'failed']);
                return response()->json(['error' => 'Payment verification failed'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Subscription payment verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Payment verification failed'], 500);
        }
    }

    /**
     * Handle subscription payment failure
     */
    public function subscriptionFailed(Request $request)
    {
        $transactionId = $request->get('transaction_id');
        $transaction = Transaction::where('transaction_id', $transactionId)
            ->where('type', 'subscription')
            ->first();

        if ($transaction) {
            $transaction->update(['status' => 'failed']);
        }

        return redirect()->route('subscription.plans')
            ->with('error', 'Subscription payment failed. Please try again.');
    }

    /**
     * Handle subscription payment success
     */
    public function subscriptionSuccess(Request $request)
    {
        $transactionId = $request->get('transaction_id');
        $transaction = Transaction::where('transaction_id', $transactionId)
            ->where('type', 'subscription')
            ->first();

        if (!$transaction || $transaction->status !== 'completed') {
            return redirect()->route('subscription.plans')
                ->with('error', 'Payment verification failed. Please contact support.');
        }

        return redirect()->route('subscription.index')
            ->with('success', 'Subscription upgraded successfully!');
    }
}
