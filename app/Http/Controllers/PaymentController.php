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

                // Check if this is a duplicate transaction error
                if (strpos($result['message'] ?? '', 'duplicate transaction') !== false || 
                    strpos($result['message'] ?? '', 'Duplicate transaction') !== false) {
                    
                    Log::warning('Duplicate transaction detected, attempting retry with new transaction ID', [
                        'original_transaction_id' => $transaction->transaction_id,
                        'yo_payments_error' => $result['message']
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
                            'yo_payments_error' => $result['message'],
                            'retry_timestamp' => now()->toISOString()
                        ])
                    ]);
                    
                    Log::info('Retrying payment with new transaction ID', [
                        'original_transaction_id' => $transaction->transaction_id,
                        'new_transaction_id' => $newTransactionId
                    ]);
                    
                    // Try the payment again with the new transaction ID
                    $retryResult = $this->yoPayments->initiatePayment($transaction, $request->phone_number);
                    
                    if ($retryResult['success']) {
                        Log::info('Payment retry successful', [
                            'original_transaction_id' => $transaction->transaction_id,
                            'new_transaction_id' => $newTransactionId,
                            'yo_payments_reference' => $retryResult['transaction_reference'] ?? null
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
                        'yo_payments_error' => $result['message'] ?? 'Unknown error',
                        'yo_payments_response' => $result,
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
     * 
     * This method receives Instant Payment Notifications (IPN) from Yo Payments
     * when payment status changes (success, failure, pending)
     */
    public function callback(Request $request)
    {
        try {
            Log::info('Yo Payments Callback Received', [
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            // Process the callback data
            $result = $this->yoPayments->processCallback($request->getContent());
            
            if ($result['success']) {
                $transactionId = $result['transaction_id'];
                $status = $result['status'];
                
                Log::info('Callback processed successfully', [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'result' => $result,
                ]);
                
                // Find the transaction
                $transaction = Transaction::where('transaction_id', $transactionId)->first();
                
                if ($transaction) {
                    $oldStatus = $transaction->status;
                    $newStatus = $this->yoPayments->mapPaymentStatus($status);
                    
                    Log::info('Transaction status update', [
                        'transaction_id' => $transactionId,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'yo_status' => $status,
                    ]);
                    
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
                            'ipn_processed_at' => now()->toISOString(),
                            'ipn_source' => 'callback',
                        ]),
                    ];

                    // If payment is completed, add completion timestamp and process
                    if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                        $updateData['paid_at'] = now();
                        
                        Log::info('Payment completed via callback - starting workflow completion', [
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

                    // Update the transaction
                    $transaction->update($updateData);
                    
                    Log::info('Transaction updated successfully', [
                        'transaction_id' => $transactionId,
                        'status' => $newStatus,
                        'update_data' => $updateData,
                    ]);
                } else {
                    Log::warning('Transaction not found for callback', [
                        'transaction_id' => $transactionId,
                        'status' => $status,
                    ]);
                }
            } else {
                Log::error('Callback processing failed', [
                    'result' => $result,
                    'request_data' => $request->all(),
                ]);
            }
            
            // Always return success to Yo Payments to prevent retries
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Yo Payments Callback Error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            
            // Return 200 OK even on error to prevent Yo Payments from retrying
            // This prevents infinite retry loops
            return response('OK', 200);
        }
    }

    /**
     * Handle failed payment notification from Yo Payments
     * 
     * According to Yo Payments API 6.4: Transaction Failure Notification API
     * Parameters: failed_transaction_reference, transaction_init_date, verification
     */
    public function failed(Request $request)
    {
        try {
            Log::info('Yo Payments Failure Notification Received', [
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);

            // Extract parameters according to Yo Payments API specification
            $failedTransactionReference = $request->input('failed_transaction_reference');
            $transactionInitDate = $request->input('transaction_init_date');
            $verification = $request->input('verification');

            // Validate required parameters
            if (!$failedTransactionReference || !$verification) {
                Log::error('Yo Payments Failure: Missing required parameters', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'verification' => $verification,
                ]);
                return response('Missing required parameters', 400);
            }

            // Verify the signature if public key is configured
            if (config('services.yo_payments.public_key_enabled', false)) {
                $isValidSignature = $this->verifyFailureNotificationSignature(
                    $failedTransactionReference,
                    $transactionInitDate,
                    $verification
                );

                if (!$isValidSignature) {
                    Log::error('Yo Payments Failure: Invalid signature', [
                        'failed_transaction_reference' => $failedTransactionReference,
                        'verification' => $verification,
                    ]);
                    return response('Invalid signature', 401);
                }

                Log::info('Yo Payments Failure: Signature verified successfully');
            } else {
                Log::warning('Yo Payments Failure: Signature verification skipped (public key not configured)');
            }

            // Find the transaction using the failed_transaction_reference
            $transaction = Transaction::where('transaction_id', $failedTransactionReference)
                ->orWhere('payment_details->yo_payments_reference', $failedTransactionReference)
                ->first();
            
            if (!$transaction) {
                Log::error('Yo Payments Failure: Transaction not found', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'search_criteria' => 'transaction_id or yo_payments_reference',
                ]);
                return response('Transaction not found', 404);
            }

            // Update transaction status to failed
            $oldStatus = $transaction->status;
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
                'payment_details' => array_merge($transaction->payment_details ?? [], [
                    'failure_notification_received_at' => now(),
                    'failure_transaction_reference' => $failedTransactionReference,
                    'failure_transaction_init_date' => $transactionInitDate,
                    'failure_verification' => $verification,
                    'failure_verification_status' => config('services.yo_payments.public_key_enabled', false) ? 'verified' : 'skipped',
                    'failure_processing_timestamp' => now()->toISOString(),
                ]),
            ]);

            Log::info('Yo Payments Failure: Transaction updated successfully', [
                'transaction_id' => $transaction->transaction_id,
                'old_status' => $oldStatus,
                'new_status' => 'failed',
                'failed_transaction_reference' => $failedTransactionReference,
                'transaction_init_date' => $transactionInitDate,
            ]);

            // Return 200 OK as required by Yo Payments API
            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Yo Payments Failure Notification Error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 500 error - Yo Payments will retry
            return response('Error processing failure notification', 500);
        }
    }

    /**
     * Verify the signature of failure notification according to Yo Payments API 6.4.3
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
            $publicKeyPath = config('services.yo_payments.public_key_path');
            
            if (!file_exists($publicKeyPath)) {
                Log::error('Yo Payments Failure: Public key file not found', [
                    'public_key_path' => $publicKeyPath,
                ]);
                return false;
            }

            // Read the public key
            $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
            
            if (!$publicKey) {
                Log::error('Yo Payments Failure: Invalid public key', [
                    'public_key_path' => $publicKeyPath,
                ]);
                return false;
            }

            // Concatenate parameters in order as per API spec 6.4.2
            $messageToVerify = $failedTransactionReference . $transactionInitDate;
            
            // Decode base64 signature
            $decodedSignature = base64_decode($verification);
            
            if ($decodedSignature === false) {
                Log::error('Yo Payments Failure: Invalid base64 signature', [
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
                Log::info('Yo Payments Failure: Signature verification successful', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'transaction_init_date' => $transactionInitDate,
                ]);
                return true;
            } elseif ($verificationResult === 0) {
                Log::error('Yo Payments Failure: Signature verification failed', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'transaction_init_date' => $transactionInitDate,
                    'verification' => $verification,
                ]);
                return false;
            } else {
                Log::error('Yo Payments Failure: Signature verification error', [
                    'error' => openssl_error_string(),
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Yo Payments Failure: Signature verification exception', [
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

            // Check status using Yo Payments API with comprehensive verification
            $yoPaymentsService = new \App\Services\YoPaymentsService();
            $statusResult = $yoPaymentsService->comprehensiveTransactionVerification($transaction);

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

    /**
     * Unified IPN handler for all payment responses (success, failure, pending)
     * 
     * This method handles all types of payment notifications from Yo! Payments:
     * - Success notifications (TransactionStatus: SUCCEEDED)
     * - Failure notifications (TransactionStatus: FAILED)
     * - Pending notifications (TransactionStatus: PENDING)
     * - Failure notifications (separate endpoint with different parameters)
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function unifiedIpn(Request $request)
    {
        try {
            Log::info('Yo Payments Unified IPN Received', [
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);

            // Determine the type of notification based on the request data
            $notificationType = $this->determineNotificationType($request);
            
            Log::info('Yo Payments IPN Type Determined', [
                'notification_type' => $notificationType,
                'request_data' => $request->all(),
            ]);

            switch ($notificationType) {
                case 'success':
                    return $this->handleSuccessNotification($request);
                    
                case 'failure':
                    return $this->handleFailureNotification($request);
                    
                case 'pending':
                    return $this->handlePendingNotification($request);
                    
                case 'failure_separate':
                    return $this->handleSeparateFailureNotification($request);
                    
                default:
                    Log::warning('Yo Payments IPN: Unknown notification type', [
                        'notification_type' => $notificationType,
                        'request_data' => $request->all(),
                    ]);
                    return response('Unknown notification type', 400);
            }

        } catch (\Exception $e) {
            Log::error('Yo Payments Unified IPN Error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Error processing IPN', 500);
        }
    }

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
        Log::info('Yo Payments Success IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Process the callback data
        $result = $this->yoPayments->processCallback($request->getContent());
        
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
                $newStatus = $this->yoPayments->mapPaymentStatus($status);
                
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
                        'yo_payments_status' => $status,
                        'yo_payments_amount' => $request->input('Amount'),
                        'yo_payments_currency' => $request->input('Currency', 'UGX'),
                        'yo_payments_receipt' => $request->input('IssuedReceiptNumber'),
                        'yo_payments_initiation_date' => $request->input('TransactionInitiationDate'),
                        'yo_payments_completion_date' => $request->input('TransactionCompletionDate'),
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
        Log::info('Yo Payments Failure IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Process the callback data
        $result = $this->yoPayments->processCallback($request->getContent());
        
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
                $newStatus = $this->yoPayments->mapPaymentStatus($status);
                
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
                        'yo_payments_status' => $status,
                        'yo_payments_amount' => $request->input('Amount'),
                        'yo_payments_currency' => $request->input('Currency', 'UGX'),
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
        Log::info('Yo Payments Pending IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Process the callback data
        $result = $this->yoPayments->processCallback($request->getContent());
        
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
                $newStatus = $this->yoPayments->mapPaymentStatus($status);
                
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
                        'yo_payments_status' => $status,
                        'yo_payments_amount' => $request->input('Amount'),
                        'yo_payments_currency' => $request->input('Currency', 'UGX'),
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
        Log::info('Yo Payments Separate Failure IPN Processing', [
            'request_data' => $request->all(),
        ]);

        // Extract parameters according to Yo Payments API specification
        $failedTransactionReference = $request->input('failed_transaction_reference');
        $transactionInitDate = $request->input('transaction_init_date');
        $verification = $request->input('verification');

        // Validate required parameters
        if (!$failedTransactionReference || !$verification) {
            Log::error('Yo Payments Separate Failure: Missing required parameters', [
                'failed_transaction_reference' => $failedTransactionReference,
                'verification' => $verification,
            ]);
            return response('Missing required parameters', 400);
        }

        // Verify the signature if public key is configured
        if (config('services.yo_payments.public_key_enabled', false)) {
            $isValidSignature = $this->verifyFailureNotificationSignature(
                $failedTransactionReference,
                $transactionInitDate,
                $verification
            );

            if (!$isValidSignature) {
                Log::error('Yo Payments Separate Failure: Invalid signature', [
                    'failed_transaction_reference' => $failedTransactionReference,
                    'verification' => $verification,
                ]);
                return response('Invalid signature', 401);
            }

            Log::info('Yo Payments Separate Failure: Signature verified successfully');
        } else {
            Log::warning('Yo Payments Separate Failure: Signature verification skipped (public key not configured)');
        }

        // Find the transaction using the failed_transaction_reference
        $transaction = Transaction::where('transaction_id', $failedTransactionReference)
            ->orWhere('payment_details->yo_payments_reference', $failedTransactionReference)
            ->first();
        
        if (!$transaction) {
            Log::error('Yo Payments Separate Failure: Transaction not found', [
                'failed_transaction_reference' => $failedTransactionReference,
                'search_criteria' => 'transaction_id or yo_payments_reference',
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
                'failure_verification_status' => config('services.yo_payments.public_key_enabled', false) ? 'verified' : 'skipped',
                'ipn_processed_at' => now()->toISOString(),
                'ipn_source' => 'unified_callback',
            ]),
        ]);

        Log::info('Yo Payments Separate Failure: Transaction updated successfully', [
            'transaction_id' => $transaction->transaction_id,
            'old_status' => $oldStatus,
            'new_status' => 'failed',
            'failed_transaction_reference' => $failedTransactionReference,
            'transaction_init_date' => $transactionInitDate,
        ]);

        return response('OK', 200);
    }
}
