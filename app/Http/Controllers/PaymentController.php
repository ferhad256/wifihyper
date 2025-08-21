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
        Log::info('Payment initiation started', [
            'request_data' => $request->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'phone_number' => 'required|string',
            'hotspot_id' => 'required|exists:hotspots,id',
        ]);

        if ($validator->fails()) {
            Log::warning('Payment validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return back()->withErrors($validator)->withInput();
        }

        try {
            $package = Package::findOrFail($request->package_id);
            $hotspot = $package->hotspot;
            $tenant = $hotspot->tenant;

            Log::info('Payment entities found', [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'package_price' => $package->price,
                'hotspot_id' => $hotspot->id,
                'hotspot_name' => $hotspot->name,
                'tenant_id' => $tenant->id,
                'tenant_email' => $tenant->email
            ]);

            // Enhanced voucher availability check with notification
            $availability = $this->voucherAvailabilityService->checkVoucherAvailability($tenant, $package);
            
            Log::info('Voucher availability check', [
                'availability_result' => $availability,
                'tenant_id' => $tenant->id,
                'package_id' => $package->id
            ]);
            
            if (!$availability['has_vouchers']) {
                Log::warning('No vouchers available for payment', [
                    'tenant_id' => $tenant->id,
                    'package_id' => $package->id,
                    'availability' => $availability
                ]);
                
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
                Log::error('Voucher not found despite availability check', [
                    'tenant_id' => $tenant->id,
                    'package_id' => $package->id,
                    'availability' => $availability
                ]);
                
                // Create notification for admin about voucher shortage
                $this->createVoucherShortageNotification($tenant, $package);
                
                return back()->with('error', 'No vouchers available for the ' . $package->name . ' package. Please contact the hotspot owner to upload more vouchers.')->withInput();
            }

            Log::info('Voucher found for payment', [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'package_id' => $package->id
            ]);

            // Calculate transaction fees
            $feeCalculation = $this->transactionFeeService->calculateFee($package->price);
            
            Log::info('Transaction fee calculation', [
                'original_amount' => $package->price,
                'fee_calculation' => $feeCalculation
            ]);
            
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

            Log::info('Transaction created successfully', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'transaction_fee' => $transaction->transaction_fee,
                'net_amount' => $transaction->net_amount,
                'phone_number' => $request->phone_number
            ]);

            // Process payment via Yo Payments
            Log::info('Initiating Yo Payments request', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'phone_number' => $request->phone_number
            ]);

            $result = $this->yoPayments->initiatePayment($transaction, $request->phone_number);

            Log::info('Yo Payments response received', [
                'transaction_id' => $transaction->transaction_id,
                'yo_payments_result' => $result,
                'success' => $result['success'] ?? false
            ]);

            if ($result['success']) {
                Log::info('Payment initiated successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'yo_payments_reference' => $result['transaction_reference'] ?? null
                ]);

                // Check if this is a simulated payment (development mode)
                if (config('app.env') === 'local' && config('app.debug') === true) {
                    Log::info('Development mode: Simulating payment success', [
                        'transaction_id' => $transaction->transaction_id
                    ]);
                    
                    // For simulated payments, redirect directly to success
                    session(['last_transaction_id' => $transaction->transaction_id]);
                    return redirect()->route('payment.success')->with('success', 'Payment completed successfully! Check your phone for the WiFi voucher code.');
                } else {
                    Log::info('Production mode: Redirecting to pending page', [
                        'transaction_id' => $transaction->transaction_id
                    ]);
                    
                    // For real payments, redirect to pending page
                    return redirect()->route('payment.pending', $transaction->transaction_id);
                }
            } else {
                Log::error('Payment initiation failed', [
                    'transaction_id' => $transaction->transaction_id,
                    'yo_payments_error' => $result['message'] ?? 'Unknown error',
                    'yo_payments_response' => $result
                ]);

                // Update transaction status to failed
                $transaction->update([
                    'status' => 'failed',
                    'payment_details' => [
                        'yo_payments_error' => $result['message'] ?? 'Unknown error',
                        'yo_payments_response' => $result,
                        'failed_at' => now()
                    ]
                ]);

                return back()->with('error', $result['message'])->withInput();
            }

        } catch (\Exception $e) {
            Log::error('Payment initiation exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return back()->with('error', 'Payment initiation failed: ' . $e->getMessage())->withInput();
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
     * Handle payment callback from Yo Payments
     */
    public function callback(Request $request)
    {
        try {
            Log::info('Yo Payments Callback Received', [
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            $result = $this->yoPayments->processCallback($request->getContent());
            
            if ($result['success']) {
                $transactionId = $result['transaction_id'];
                $status = $result['status'];
                
                // Find the transaction
                $transaction = Transaction::where('transaction_id', $transactionId)->first();
                
                if ($transaction) {
                    $oldStatus = $transaction->status;
                    $newStatus = $this->yoPayments->mapPaymentStatus($status);
                    
                    // Update transaction with callback data and billing information
                    $updateData = [
                        'status' => $newStatus,
                        'payment_details' => array_merge($transaction->payment_details ?? [], [
                            'callback_received_at' => now(),
                            'callback_status' => $status,
                            'callback_amount' => $request->input('Amount'),
                            'callback_currency' => $request->input('Currency', 'UGX'),
                            'yo_payments_status' => $status,
                            'yo_payments_amount' => $request->input('Amount'),
                            'yo_payments_currency' => $request->input('Currency', 'UGX'),
                            'yo_payments_receipt' => $request->input('IssuedReceiptNumber'),
                            'yo_payments_initiation_date' => $request->input('TransactionInitiationDate'),
                            'yo_payments_completion_date' => $request->input('TransactionCompletionDate'),
                        ]),
                    ];

                    // If payment is completed, add completion timestamp and process
                    if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                        $updateData['paid_at'] = now();
                        
                        // Update wallet balance and send SMS
                        $this->updateWalletBalance($transactionId);
                        $this->sendVoucherSms($transactionId);
                        
                        // Store transaction ID in session for success page
                        session(['last_transaction_id' => $transactionId]);
                        
                        Log::info('Payment completed via callback', [
                            'transaction_id' => $transactionId,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'amount' => $transaction->amount,
                            'phone_number' => $transaction->phone_number,
                            'receipt_number' => $request->input('IssuedReceiptNumber'),
                        ]);
                    }

                    // If payment failed, add failure timestamp
                    if ($newStatus === 'failed' && $oldStatus !== 'failed') {
                        $updateData['failed_at'] = now();
                        
                        Log::warning('Payment failed via callback', [
                            'transaction_id' => $transactionId,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'amount' => $transaction->amount,
                            'phone_number' => $transaction->phone_number,
                        ]);
                    }

                    $transaction->update($updateData);
                }
            }
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('Yo Payments Callback Error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
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
     * Check payment status using Yo Payments API
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
                $oldStatus = $transaction->status;
                $newStatus = $statusResult['status'];
                
                // Update transaction with latest status and billing details
                $updateData = [
                    'status' => $newStatus,
                    'payment_details' => array_merge($transaction->payment_details ?? [], [
                        'last_status_check' => now(),
                        'status_check_result' => $statusResult['data'],
                        'transaction_details' => $statusResult['transaction_details'] ?? [],
                        'yo_payments_status' => $statusResult['data']['Status'] ?? 'unknown',
                        'yo_payments_amount' => $statusResult['data']['Amount'] ?? null,
                        'yo_payments_currency' => $statusResult['data']['Currency'] ?? 'UGX',
                        'yo_payments_receipt' => $statusResult['transaction_details']['receipt_number'] ?? null,
                        'yo_payments_initiation_date' => $statusResult['transaction_details']['initiation_date'] ?? null,
                        'yo_payments_completion_date' => $statusResult['transaction_details']['completion_date'] ?? null,
                    ]),
                ];

                // If payment is completed, add completion timestamp
                if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                    $updateData['paid_at'] = now();
                    
                    // Update wallet balance and send SMS
                    $this->updateWalletBalance($transactionId);
                    $this->sendVoucherSms($transactionId);
                    
                    // Store transaction ID in session for success page
                    session(['last_transaction_id' => $transactionId]);
                    
                    Log::info('Payment completed successfully', [
                        'transaction_id' => $transactionId,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'amount' => $transaction->amount,
                        'phone_number' => $transaction->phone_number,
                    ]);
                }

                // If payment failed, add failure timestamp
                if ($newStatus === 'failed' && $oldStatus !== 'failed') {
                    $updateData['failed_at'] = now();
                    
                    Log::warning('Payment failed', [
                        'transaction_id' => $transactionId,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'amount' => $transaction->amount,
                        'phone_number' => $transaction->phone_number,
                    ]);
                }

                $transaction->update($updateData);

                return response()->json([
                    'success' => true,
                    'status' => $newStatus,
                    'is_pending' => $statusResult['is_pending'] ?? false,
                    'transaction_details' => $statusResult['transaction_details'] ?? [],
                    'billing_info' => [
                        'amount' => $transaction->amount,
                        'transaction_fee' => $transaction->transaction_fee,
                        'net_amount' => $transaction->net_amount,
                        'currency' => $transaction->currency,
                        'receipt_number' => $statusResult['transaction_details']['receipt_number'] ?? null,
                        'initiation_date' => $statusResult['transaction_details']['initiation_date'] ?? null,
                        'completion_date' => $statusResult['transaction_details']['completion_date'] ?? null,
                    ],
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
                'trace' => $e->getTraceAsString(),
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

            // Calculate transaction fees
            $transactionFeeService = new \App\Services\TransactionFeeService();
            $feeCalculation = $transactionFeeService->calculateFee($voucher->package ? $voucher->package->price : 0);
            
            // Create manual transaction
            $transaction = Transaction::create([
                'tenant_id' => $voucher->tenant_id,
                'package_id' => $voucher->package_id,
                'voucher_id' => $voucher->id,
                'transaction_id' => 'MANUAL_' . time(),
                'amount' => $voucher->package ? $voucher->package->price : 0,
                'transaction_fee' => $feeCalculation['fee_amount'],
                'net_amount' => $feeCalculation['net_amount'],
                'fee_percentage' => $feeCalculation['fee_percentage'],
                'currency' => 'UGX',
                'status' => 'completed',
                'phone_number' => $request->phone_number,
                'paid_at' => now(),
            ]);

            // Update tenant wallet balance
            $tenant = $voucher->tenant;
            $tenant->wallet_balance += $transaction->net_amount;
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
                
                // Verify voucher is valid and unused
                if ($voucher->status === 'unused' && $voucher->package_id === $transaction->package_id) {
                    $smsService = new UgSmsService();
                    $smsResult = $smsService->sendVoucherCode(
                        $transaction->phone_number,
                        $voucher->code,
                        $transaction->package
                    );

                    if ($smsResult['success']) {
                        // Mark voucher as used
                        $voucher->update([
                            'status' => 'used',
                            'used_at' => now(),
                            'phone_number' => $transaction->phone_number,
                        ]);

                        Log::info('Voucher SMS sent successfully and voucher marked as used', [
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
