<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\Voucher;
use App\Models\Notification;
use App\Models\SubscriptionPlan;
use App\Services\JpesaService;
use App\Services\UgSmsService;
use App\Services\VoucherAvailabilityService;
use App\Services\TransactionFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $jpesaService;
    protected $voucherAvailabilityService;
    protected $transactionFeeService;

    public function __construct()
    {
        $this->jpesaService = new JpesaService();
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
            'payment_gateway' => 'nullable|string|in:jpesa',
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
            
            // Create transaction with unique ID
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

            // Use JPesa as the only payment gateway
            $paymentGateway = 'jpesa';
            
            Log::info('Initiating payment request', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'phone_number' => $request->phone_number,
                'payment_gateway' => $paymentGateway
            ]);

            // Process payment via JPesa
            $result = $this->jpesaService->initiatePayment($transaction, $request->phone_number);

            Log::info('JPesa response received', [
                'transaction_id' => $transaction->transaction_id,
                'jpesa_result' => $result,
                'success' => $result['success'] ?? false
            ]);

            if ($result['success']) {
                Log::info('Payment initiated successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'jpesa_reference' => $result['data']['jpesa_tid'] ?? null
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
                    'jpesa_error' => $result['message'] ?? 'Unknown error',
                    'jpesa_response' => $result
                ]);

                // Check if this is a duplicate transaction error
                if (strpos($result['message'] ?? '', 'duplicate transaction') !== false || 
                    strpos($result['message'] ?? '', 'Duplicate transaction') !== false) {
                    
                    Log::warning('Duplicate transaction detected, attempting retry with new transaction ID', [
                        'original_transaction_id' => $transaction->transaction_id,
                        'jpesa_error' => $result['message']
                    ]);
                    
                    // Generate a completely new transaction ID with different timestamp
                    sleep(1); // Wait 1 second to ensure different timestamp
                    $newTransactionId = 'TXN_' . time() . '_' . rand(1000, 9999);
                    
                    // Update the transaction with the new ID
                    $transaction->update([
                        'transaction_id' => $newTransactionId,
                        'payment_details' => array_merge($transaction->payment_details ?? [], [
                            'original_transaction_id' => $transaction->transaction_id,
                            'retry_attempt' => 1,
                            'retry_reason' => 'duplicate_transaction',
                            'jpesa_error' => $result['message'],
                            'retry_timestamp' => now()->toISOString()
                        ])
                    ]);
                    
                    Log::info('Retrying payment with new transaction ID', [
                        'original_transaction_id' => $transaction->transaction_id,
                        'new_transaction_id' => $newTransactionId
                    ]);
                    
                    // Try the payment again with the new transaction ID
                    
                    if ($retryResult['success']) {
                        Log::info('Payment retry successful', [
                            'original_transaction_id' => $transaction->transaction_id,
                            'new_transaction_id' => $newTransactionId,
                            'jpesa_reference' => $retryResult['transaction_reference'] ?? null
                        ]);
                        
                        return redirect()->route('payment.pending', $newTransactionId);
                    } else {
                        Log::error('Payment retry failed', [
                            'original_transaction_id' => $transaction->transaction_id,
                            'new_transaction_id' => $newTransactionId,
                            'retry_error' => $retryResult['message']
                        ]);
                    }
                }

                // Update transaction status to failed
                $transaction->update([
                    'status' => 'failed',
                    'payment_details' => array_merge($transaction->payment_details ?? [], [
                        'jpesa_error' => $result['message'] ?? 'Unknown error',
                        'jpesa_response' => $result,
                        'failed_at' => now()
                    ])
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

    /**
     * Handle payment callback from JPesa
     * 
     * This method receives Instant Payment Notifications (IPN) from JPesa
     * when payment status changes (success, failure, pending)
     */

    /**
     * Handle failed payment notification from JPesa
     * 
     * According to JPesa API specification
     * Parameters: failed_transaction_reference, transaction_init_date, verification
     */

    /**
     * Verify the signature of failure notification according to JPesa API specification
     * 
     * @param string $failedTransactionReference
     * @param string $transactionInitDate
     * @param string $verification Base64 encoded RSA signature
     * @return bool
     */
    private function verifyFailureNotificationSignature($failedTransactionReference, $transactionInitDate, $verification)
    {
        try {
            // Get the public key path
            $publicKeyPath = config('services.jpesa.public_key_path');
            
            if (!file_exists($publicKeyPath)) {
                Log::error('JPesa Failure: Public key file not found', [
                    'public_key_path' => $publicKeyPath,
                ]);
                return false;
            }

            // Read the public key
            $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
            
            if (!$publicKey) {
                Log::error('JPesa Failure: Invalid public key', [
                    'public_key_path' => $publicKeyPath,
                ]);
                return false;
            }

            // Concatenate parameters in order as per API spec 6.4.2
            $messageToVerify = $failedTransactionReference . $transactionInitDate;
            
            // Decode base64 signature
            $decodedSignature = base64_decode($verification);
            
            if ($decodedSignature === false) {
                Log::error('JPesa Failure: Invalid base64 signature', [
                    'verification' => $verification,
                ]);
                return false;
            }

            // Verify the signature
            $verificationResult = openssl_verify(
                $messageToVerify,
                $decodedSignature,
                $publicKey,
                OPENSSL_ALGO_SHA1
            );

            // Free the key
            openssl_free_key($publicKey);

            if ($verificationResult === 1) {
                Log::info('JPesa Failure: Signature verification successful', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'transaction_init_date' => $transactionInitDate,
                ]);
                return true;
            } elseif ($verificationResult === 0) {
                Log::error('JPesa Failure: Signature verification failed', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'transaction_init_date' => $transactionInitDate,
                    'verification' => $verification,
                ]);
                return false;
            } else {
                Log::error('JPesa Failure: Signature verification error', [
                    'error' => openssl_error_string(),
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('JPesa Failure: Signature verification exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
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
     * Check payment status for a transaction
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
            
            Log::info('Payment status check', [
                'transaction_id' => $transactionId,
                'current_status' => $transaction->status,
                'paid_at' => $transaction->paid_at,
                'created_at' => $transaction->created_at
            ]);
            
            return response()->json([
                'success' => true,
                'status' => $transaction->status,
                'message' => $this->getStatusMessage($transaction->status),
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'paid_at' => $transaction->paid_at,
                'voucher_code' => $transaction->voucher ? $transaction->voucher->code : null,
                'package_name' => $transaction->package ? $transaction->package->name : null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status'
            ], 500);
        }
    }
    
    /**
     * Get status message for display
     */
    private function getStatusMessage($status)
    {
        switch ($status) {
            case 'completed':
                return 'Payment completed successfully';
            case 'pending':
                return 'Payment is being processed';
            case 'failed':
                return 'Payment failed';
            default:
                return 'Payment status unknown';
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

    /**
     * Initiate subscription payment
     */

    /**
     * Handle subscription payment callback
     */

    /**
     * Handle subscription payment failure
     */

    /**
     * Handle subscription payment success
     */

    /**
     * Unified IPN handler for all payment responses (success, failure, pending)
     * 
     * This method handles all types of payment notifications from JPesa:
     * - Success notifications (TransactionStatus: SUCCEEDED)
     * - Failure notifications (TransactionStatus: FAILED)
     * - Pending notifications (TransactionStatus: PENDING)
     * - Failure notifications (separate endpoint with different parameters)
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */

    /**
     * Determine the type of notification based on request data
     * 
     * @param Request $request
     * @return string
     */
    protected function determineNotificationType(Request $request)
    {
        $data = $request->all();
        
        // Check for separate failure notification (has failed_transaction_reference)
        if ($request->has('failed_transaction_reference')) {
            return 'failure_separate';
        }
        
        // Check for standard notification with TransactionStatus
        if ($request->has('TransactionStatus')) {
            $status = strtoupper($request->input('TransactionStatus'));
            
            switch ($status) {
                case 'SUCCEEDED':
                    return 'success';
                case 'FAILED':
                    return 'failure';
                case 'PENDING':
                    return 'pending';
                default:
                    return 'unknown';
            }
        }
        
        // Check for other indicators
        if ($request->has('IssuedReceiptNumber')) {
            return 'success';
        }
        
        if ($request->has('verification')) {
            return 'failure_separate';
        }
        
        return 'unknown';
    }

    /**
     * Handle success notification
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    protected function handleSuccessNotification(Request $request)
    {
        Log::info('JPesa Success IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Process the callback data
        
        if ($result['success']) {
            $transactionId = $result['transaction_id'];
            $status = $result['status'];
            
            Log::info('Success IPN processed successfully', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'result' => $result,
            ]);
            
            // Find the transaction
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            
            if ($transaction) {
                $oldStatus = $transaction->status;
                
                Log::info('Transaction status update', [
                    'transaction_id' => $transactionId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'yo_status' => $status,
                ]);
                
                // Update transaction with callback data
                $updateData = [
                    'status' => $newStatus,
                    'payment_details' => array_merge($transaction->payment_details ?? [], [
                        'ipn_received_at' => now(),
                        'ipn_type' => 'success',
                        'ipn_status' => $status,
                        'ipn_amount' => $request->input('Amount'),
                        'ipn_currency' => $request->input('Currency', 'UGX'),
                        'jpesa_status' => $status,
                        'jpesa_amount' => $request->input('Amount'),
                        'jpesa_currency' => $request->input('Currency', 'UGX'),
                        'jpesa_receipt' => $request->input('IssuedReceiptNumber'),
                        'jpesa_initiation_date' => $request->input('TransactionInitiationDate'),
                        'jpesa_completion_date' => $request->input('TransactionCompletionDate'),
                        'ipn_processed_at' => now()->toISOString(),
                        'ipn_source' => 'unified_callback',
                    ]),
                ];

                // If payment is completed, add completion timestamp and process
                if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                    $updateData['paid_at'] = now();
                    
                    Log::info('Payment completed via unified IPN - starting workflow completion', [
                        'transaction_id' => $transactionId,
                        'amount' => $transaction->amount,
                        'phone_number' => $transaction->phone_number,
                    ]);
                    
                    try {
                        // Update wallet balance and send SMS
                        $this->updateWalletBalance($transactionId);
                        $this->sendVoucherSms($transactionId);
                        
                        // Store transaction ID in session for success page
                        session(['last_transaction_id' => $transactionId]);
                        
                        Log::info('Payment workflow completed successfully', [
                            'transaction_id' => $transactionId,
                            'wallet_updated' => true,
                            'sms_sent' => true,
                        ]);
                    } catch (\Exception $workflowError) {
                        Log::error('Payment workflow completion failed', [
                            'transaction_id' => $transactionId,
                            'error' => $workflowError->getMessage(),
                            'trace' => $workflowError->getTraceAsString(),
                        ]);
                        
                        // Still update the transaction status, but log the workflow error
                        $updateData['payment_details']['workflow_error'] = $workflowError->getMessage();
                    }
                }

                $transaction->update($updateData);
                
                Log::info('Success IPN processed and transaction updated', [
                    'transaction_id' => $transactionId,
                    'new_status' => $newStatus,
                ]);
            } else {
                Log::error('Success IPN: Transaction not found', [
                    'transaction_id' => $transactionId,
                ]);
            }
        } else {
            Log::error('Success IPN: Failed to process callback', [
                'result' => $result,
            ]);
        }
        
        return response('OK', 200);
    }

    /**
     * Handle failure notification
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    protected function handleFailureNotification(Request $request)
    {
        Log::info('JPesa Failure IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Process the callback data
        
        if ($result['success']) {
            $transactionId = $result['transaction_id'];
            $status = $result['status'];
            
            Log::info('Failure IPN processed successfully', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'result' => $result,
            ]);
            
            // Find the transaction
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            
            if ($transaction) {
                $oldStatus = $transaction->status;
                
                Log::info('Transaction status update (failure)', [
                    'transaction_id' => $transactionId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'yo_status' => $status,
                ]);
                
                // Update transaction with failure data
                $transaction->update([
                    'status' => $newStatus,
                    'failed_at' => now(),
                    'payment_details' => array_merge($transaction->payment_details ?? [], [
                        'ipn_received_at' => now(),
                        'ipn_type' => 'failure',
                        'ipn_status' => $status,
                        'ipn_amount' => $request->input('Amount'),
                        'ipn_currency' => $request->input('Currency', 'UGX'),
                        'jpesa_status' => $status,
                        'jpesa_amount' => $request->input('Amount'),
                        'jpesa_currency' => $request->input('Currency', 'UGX'),
                        'ipn_processed_at' => now()->toISOString(),
                        'ipn_source' => 'unified_callback',
                        'failure_reason' => $request->input('StatusMessage', 'Payment failed'),
                    ]),
                ]);
                
                Log::info('Failure IPN processed and transaction updated', [
                    'transaction_id' => $transactionId,
                    'new_status' => $newStatus,
                ]);
            } else {
                Log::error('Failure IPN: Transaction not found', [
                    'transaction_id' => $transactionId,
                ]);
            }
        } else {
            Log::error('Failure IPN: Failed to process callback', [
                'result' => $result,
            ]);
        }
        
        return response('OK', 200);
    }

    /**
     * Handle pending notification
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    protected function handlePendingNotification(Request $request)
    {
        Log::info('JPesa Pending IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Process the callback data
        
        if ($result['success']) {
            $transactionId = $result['transaction_id'];
            $status = $result['status'];
            
            Log::info('Pending IPN processed successfully', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'result' => $result,
            ]);
            
            // Find the transaction
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            
            if ($transaction) {
                $oldStatus = $transaction->status;
                
                Log::info('Transaction status update (pending)', [
                    'transaction_id' => $transactionId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'yo_status' => $status,
                ]);
                
                // Update transaction with pending data
                $transaction->update([
                    'status' => $newStatus,
                    'payment_details' => array_merge($transaction->payment_details ?? [], [
                        'ipn_received_at' => now(),
                        'ipn_type' => 'pending',
                        'ipn_status' => $status,
                        'ipn_amount' => $request->input('Amount'),
                        'ipn_currency' => $request->input('Currency', 'UGX'),
                        'jpesa_status' => $status,
                        'jpesa_amount' => $request->input('Amount'),
                        'jpesa_currency' => $request->input('Currency', 'UGX'),
                        'ipn_processed_at' => now()->toISOString(),
                        'ipn_source' => 'unified_callback',
                    ]),
                ]);
                
                Log::info('Pending IPN processed and transaction updated', [
                    'transaction_id' => $transactionId,
                    'new_status' => $newStatus,
                ]);
            } else {
                Log::error('Pending IPN: Transaction not found', [
                    'transaction_id' => $transactionId,
                ]);
            }
        } else {
            Log::error('Pending IPN: Failed to process callback', [
                'result' => $result,
            ]);
        }
        
        return response('OK', 200);
    }

    /**
     * Handle separate failure notification (different format)
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    protected function handleSeparateFailureNotification(Request $request)
    {
        Log::info('JPesa Separate Failure IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Extract parameters according to JPesa API specification
        $failedTransactionReference = $request->input('failed_transaction_reference');
        $transactionInitDate = $request->input('transaction_init_date');
        $verification = $request->input('verification');

        // Validate required parameters
        if (!$failedTransactionReference || !$verification) {
            Log::error('JPesa Separate Failure: Missing required parameters', [
                'failed_transaction_reference' => $failedTransactionReference,
                'verification' => $verification,
            ]);
            return response('Missing required parameters', 400);
        }

        // Verify the signature if public key is configured
        if (config('services.jpesa.public_key_enabled', false)) {
            $isValidSignature = $this->verifyFailureNotificationSignature(
                $failedTransactionReference,
                $transactionInitDate,
                $verification
            );

            if (!$isValidSignature) {
                Log::error('JPesa Separate Failure: Invalid signature', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'verification' => $verification,
                ]);
                return response('Invalid signature', 401);
            }

            Log::info('JPesa Separate Failure: Signature verified successfully');
        } else {
            Log::warning('JPesa Separate Failure: Signature verification skipped (public key not configured)');
        }

        // Find the transaction using the failed_transaction_reference
        $transaction = Transaction::where('transaction_id', $failedTransactionReference)
            ->orWhere('payment_details->jpesa_reference', $failedTransactionReference)
            ->first();
        
        if (!$transaction) {
            Log::error('JPesa Separate Failure: Transaction not found', [
                'failed_transaction_reference' => $failedTransactionReference,
                'search_criteria' => 'transaction_id or jpesa_reference',
            ]);
            return response('Transaction not found', 404);
        }

        // Update transaction status to failed
        $oldStatus = $transaction->status;
        $transaction->update([
            'status' => 'failed',
            'failed_at' => now(),
            'payment_details' => array_merge($transaction->payment_details ?? [], [
                'ipn_received_at' => now(),
                'ipn_type' => 'failure_separate',
                'failure_notification_received_at' => now(),
                'failure_transaction_reference' => $failedTransactionReference,
                'failure_transaction_init_date' => $transactionInitDate,
                'failure_verification' => $verification,
                'failure_verification_status' => config('services.jpesa.public_key_enabled', false) ? 'verified' : 'skipped',
                'ipn_processed_at' => now()->toISOString(),
                'ipn_source' => 'unified_callback',
            ]),
        ]);

        Log::info('JPesa Separate Failure: Transaction updated successfully', [
            'transaction_id' => $transaction->transaction_id,
            'old_status' => $oldStatus,
            'new_status' => 'failed',
            'failed_transaction_reference' => $failedTransactionReference,
            'transaction_init_date' => $transactionInitDate,
        ]);

        return response('OK', 200);
    }

    /**
     * Handle JPesa callback
     */
    public function jpesaCallback(Request $request)
    {
        Log::info('JPesa callback received', [
            'callback_data' => $request->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'headers' => $request->headers->all()
        ]);

        try {
            $callbackData = $request->all();
            
            // Validate callback data structure
            if (empty($callbackData)) {
                Log::error('JPesa callback: Empty callback data received', [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                return response('ERROR: Empty callback data', 400);
            }

            // Log callback data for debugging
            Log::info('JPesa callback data structure', [
                'has_tx' => isset($callbackData['tx']),
                'has_tid' => isset($callbackData['tid']),
                'has_api_status' => isset($callbackData['api_status']),
                'has_msg' => isset($callbackData['msg']),
                'has_memo' => isset($callbackData['memo']),
                'callback_keys' => array_keys($callbackData)
            ]);
            
            // Handle the callback using JpesaService
            $result = $this->jpesaService->handleCallback($callbackData);
            
            if ($result) {
                Log::info('JPesa callback processed successfully', [
                    'callback_data' => $callbackData,
                    'transaction_id' => $callbackData['tx'] ?? 'unknown',
                    'jpesa_tid' => $callbackData['tid'] ?? 'unknown'
                ]);
                return response('OK', 200);
            } else {
                Log::error('JPesa callback processing failed', [
                    'callback_data' => $callbackData,
                    'transaction_id' => $callbackData['tx'] ?? 'unknown',
                    'jpesa_tid' => $callbackData['tid'] ?? 'unknown'
                ]);
                return response('ERROR: Callback processing failed', 400);
            }
            
        } catch (\Exception $e) {
            Log::error('JPesa callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'callback_data' => $request->all(),
                'ip_address' => $request->ip()
            ]);
            
            return response('ERROR: Internal server error', 500);
        }
    }
}
