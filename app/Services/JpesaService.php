<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JpesaService
{
    protected $baseUrl;
    protected $apiKey;
    protected $timeout;

    public function __construct()
    {
        // Debug configuration loading
        $apiKey = config('services.jpesa.api_key');
        
        Log::info('JpesaService: Configuration loaded', [
            'api_key' => $apiKey ? 'SET' : 'NOT SET',
            'base_url' => config('services.jpesa.base_url'),
            'timeout' => config('services.jpesa.timeout'),
        ]);
        
        $this->baseUrl = config('services.jpesa.base_url', 'https://my.jpesa.com/api/');
        $this->apiKey = $apiKey;
        $this->timeout = config('services.jpesa.timeout', 400);
        
        // Validate required credentials
        if (empty($this->apiKey)) {
            Log::error('JpesaService: Missing required API key', [
                'api_key_set' => !empty($this->apiKey),
                'config_services' => config('services'),
            ]);
        }
    }

    /**
     * Initialize a payment transaction using JPesa API
     * 
     * This method implements the JPesa API specification for
     * initiating pull-based deposits from mobile money accounts.
     * 
     * @param Transaction $transaction - Transaction model instance
     * @param string $phoneNumber - Customer phone number
     * @param array $additionalParams - Optional additional parameters
     * @return array
     */
    public function initiatePayment(Transaction $transaction, $phoneNumber, $additionalParams = [])
    {
        Log::info('JpesaService: Payment initiation started', [
            'transaction_id' => $transaction->transaction_id,
            'amount' => $transaction->amount,
            'phone_number' => $phoneNumber,
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'timestamp' => now()->toISOString()
        ]);

        // Check if we're in test mode (for development)
        // Temporarily disabled for production testing
        if (false && config('app.env') === 'local') {
            Log::info('JpesaService: Using development mode - simulating payment', [
                'transaction_id' => $transaction->transaction_id
            ]);
            return $this->simulatePayment($transaction, $phoneNumber);
        }

        try {
            // Validate mandatory parameters
            $this->validatePaymentParameters($transaction, $phoneNumber);

            Log::info('JpesaService: Building payment parameters', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'phone_number' => $phoneNumber
            ]);

            // Build XML request according to JPesa API specification
            $xmlData = $this->buildXmlRequest($transaction, $phoneNumber, $additionalParams);

            Log::info('JpesaService: XML request built', [
                'transaction_id' => $transaction->transaction_id,
                'xml_length' => strlen($xmlData)
            ]);

            // Make API request
            Log::info('JpesaService: Making request to JPesa API', [
                'transaction_id' => $transaction->transaction_id,
                'url' => $this->baseUrl
            ]);

            $response = $this->makeXmlRequest($xmlData);

            Log::info('JpesaService: JPesa API response received', [
                'transaction_id' => $transaction->transaction_id,
                'response_length' => strlen($response),
                'response_preview' => substr($response, 0, 200)
            ]);

            // Parse JSON response
            $responseData = json_decode($response, true);

            if ($responseData === null) {
                Log::error('JpesaService: Failed to parse JSON response', [
                    'transaction_id' => $transaction->transaction_id,
                    'raw_response' => $response
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Invalid response from payment gateway',
                    'data' => null
                ];
            }

            Log::info('JpesaService: Response parsed successfully', [
                'transaction_id' => $transaction->transaction_id,
                'api_status' => $responseData['api_status'] ?? 'unknown',
                'tid' => $responseData['tid'] ?? null,
                'memo' => $responseData['memo'] ?? null
            ]);

            // Check if payment was initiated successfully
            if (isset($responseData['api_status']) && $responseData['api_status'] === 'success') {
                Log::info('JpesaService: Payment initiated successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'jpesa_tid' => $responseData['tid'],
                    'jpesa_memo' => $responseData['memo'],
                    'message' => $responseData['msg'] ?? 'Payment initiated'
                ]);

                // Update transaction with JPesa details
                $transaction->update([
                    'status' => 'pending',
                    'payment_details' => array_merge($transaction->payment_details ?? [], [
                        'jpesa_tid' => $responseData['tid'],
                        'jpesa_memo' => $responseData['memo'],
                        'jpesa_message' => $responseData['msg'] ?? 'Payment initiated',
                        'jpesa_api_log' => $responseData['_api_log_'] ?? null,
                        'initiated_at' => now()->toISOString()
                    ])
                ]);

                return [
                    'success' => true,
                    'message' => $responseData['msg'] ?? 'Payment initiated successfully',
                    'data' => [
                        'transaction_id' => $transaction->transaction_id,
                        'jpesa_tid' => $responseData['tid'],
                        'jpesa_memo' => $responseData['memo'],
                        'status' => 'pending'
                    ]
                ];
            } else {
                Log::error('JpesaService: Payment initiation failed', [
                    'transaction_id' => $transaction->transaction_id,
                    'api_status' => $responseData['api_status'] ?? 'unknown',
                    'message' => $responseData['msg'] ?? 'Unknown error',
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'message' => $responseData['msg'] ?? 'Payment initiation failed',
                    'data' => $responseData
                ];
            }

        } catch (\Exception $e) {
            Log::error('JpesaService: Payment initiation exception', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Build XML request for JPesa API (Credit - Payment from customer)
     */
    protected function buildXmlRequest(Transaction $transaction, $phoneNumber, $additionalParams = [])
    {
        $callbackUrl = $this->buildCallbackUrl($additionalParams);
        $description = $this->buildPaymentDescription($transaction, $additionalParams);
        
        // Build XML exactly as per JPesa API specification
        $xmlData = '<?xml version="1.0" encoding="ISO-8859-1"?>
<g7bill>
    <_key_>' . htmlspecialchars($this->apiKey) . '</_key_>
    <cmd>account</cmd>
    <action>credit</action>
    <pt>mm</pt>
    <mobile>' . htmlspecialchars($this->formatPhoneNumber($phoneNumber)) . '</mobile>
    <amount>' . htmlspecialchars(number_format($transaction->amount, 0, '', '')) . '</amount>
    <callback>' . htmlspecialchars($callbackUrl) . '</callback>
    <tx>' . htmlspecialchars($transaction->transaction_id) . '</tx>
    <description>' . htmlspecialchars($description) . '</description>
</g7bill>';

        Log::info('JpesaService: XML request built', [
            'transaction_id' => $transaction->transaction_id,
            'action' => 'credit',
            'amount' => $transaction->amount,
            'mobile' => $this->formatPhoneNumber($phoneNumber),
            'callback' => $callbackUrl,
            'api_key_length' => strlen($this->apiKey),
            'api_key_preview' => substr($this->apiKey, 0, 8) . '...',
            'xml_preview' => substr($xmlData, 0, 200)
        ]);

        return $xmlData;
    }

    /**
     * Build XML request for JPesa withdrawal (Debit - Payment to customer)
     */
    protected function buildWithdrawalXmlRequest($phoneNumber, $amount, $transactionId, $description = '', $callbackUrl = '')
    {
        // Build XML exactly as per JPesa withdrawal example
        $xmlData = '<?xml version="1.0" encoding="ISO-8859-1"?>
<g7bill>
    <_key_>' . htmlspecialchars($this->apiKey) . '</_key_>
    <cmd>account</cmd>
    <action>debit</action>
    <pt>mm</pt>
    <mobile>' . htmlspecialchars($this->formatPhoneNumber($phoneNumber)) . '</mobile>
    <amount>' . htmlspecialchars(number_format($amount, 0, '', '')) . '</amount>
    <callback>' . htmlspecialchars($callbackUrl) . '</callback>
    <tx>' . htmlspecialchars($transactionId) . '</tx>
    <description>' . htmlspecialchars($description) . '</description>
</g7bill>';

        Log::info('JpesaService: Withdrawal XML request built', [
            'transaction_id' => $transactionId,
            'action' => 'debit',
            'amount' => $amount,
            'mobile' => $this->formatPhoneNumber($phoneNumber),
            'callback' => $callbackUrl
        ]);

        return $xmlData;
    }

    /**
     * Initiate a withdrawal transaction using JPesa API
     * 
     * This method implements the JPesa API specification for
     * initiating push-based payments to mobile money accounts.
     * 
     * @param string $phoneNumber - Customer phone number
     * @param float $amount - Amount to withdraw
     * @param string $transactionId - Unique transaction identifier
     * @param string $description - Transaction description
     * @param string $callbackUrl - Optional callback URL
     * @return array
     */
    public function initiateWithdrawal($phoneNumber, $amount, $transactionId, $description = '', $callbackUrl = '')
    {
        Log::info('JpesaService: Withdrawal initiation started', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'phone_number' => $phoneNumber,
            'description' => $description
        ]);

        try {
            // Validate parameters
            if (empty($this->apiKey)) {
                throw new \Exception('JPesa API Key is not configured.');
            }
            if (empty($amount) || $amount <= 0) {
                throw new \Exception('Invalid withdrawal amount.');
            }
            if (empty($phoneNumber)) {
                throw new \Exception('Phone number is required for withdrawal.');
            }
            if (empty($transactionId)) {
                throw new \Exception('Transaction ID is required.');
            }

            // Build XML request for withdrawal
            $xmlData = $this->buildWithdrawalXmlRequest($phoneNumber, $amount, $transactionId, $description, $callbackUrl);

            // Make API request
            $response = $this->makeXmlRequest($xmlData);

            // Parse JSON response
            $responseData = json_decode($response, true);

            if ($responseData === null) {
                Log::error('JpesaService: Failed to parse withdrawal JSON response', [
                    'transaction_id' => $transactionId,
                    'raw_response' => $response
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Invalid response from payment gateway',
                    'data' => null
                ];
            }

            Log::info('JpesaService: Withdrawal response parsed', [
                'transaction_id' => $transactionId,
                'api_status' => $responseData['api_status'] ?? 'unknown',
                'tid' => $responseData['tid'] ?? null,
                'memo' => $responseData['memo'] ?? null
            ]);

            // Check if withdrawal was initiated successfully
            if (isset($responseData['api_status']) && $responseData['api_status'] === 'success') {
                Log::info('JpesaService: Withdrawal initiated successfully', [
                    'transaction_id' => $transactionId,
                    'jpesa_tid' => $responseData['tid'],
                    'jpesa_memo' => $responseData['memo'],
                    'message' => $responseData['msg'] ?? 'Withdrawal initiated'
                ]);

                return [
                    'success' => true,
                    'message' => $responseData['msg'] ?? 'Withdrawal initiated successfully',
                    'data' => [
                        'jpesa_tid' => $responseData['tid'],
                        'jpesa_memo' => $responseData['memo'],
                        'jpesa_message' => $responseData['msg'] ?? 'Withdrawal initiated',
                        'jpesa_api_log' => $responseData['_api_log_'] ?? null,
                        'api_status' => $responseData['api_status']
                    ]
                ];
            } else {
                Log::error('JpesaService: Withdrawal initiation failed', [
                    'transaction_id' => $transactionId,
                    'api_status' => $responseData['api_status'] ?? 'unknown',
                    'message' => $responseData['msg'] ?? 'Unknown error',
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'message' => $responseData['msg'] ?? 'Withdrawal initiation failed',
                    'data' => $responseData
                ];
            }

        } catch (\Exception $e) {
            Log::error('JpesaService: Withdrawal initiation exception', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Withdrawal initiation failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Make XML request to JPesa API
     */
    protected function makeXmlRequest($xmlData)
    {
        Log::info('JpesaService: makeXmlRequest started', [
            'url' => $this->baseUrl,
            'xml_length' => strlen($xmlData),
            'xml_content' => $xmlData
        ]);

        try {
            // Use raw cURL instead of Laravel HTTP client
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: text/xml',
                'User-Agent: curl/7.68.0'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                Log::error('JpesaService: cURL error', [
                    'error' => $error
                ]);
                throw new \Exception('cURL error: ' . $error);
            }

            Log::info('JpesaService: API request completed', [
                'status' => $httpCode,
                'response_length' => strlen($response)
            ]);

            if ($httpCode >= 200 && $httpCode < 300) {
                return $response;
            } else {
                Log::error('JpesaService: API request failed', [
                    'status' => $httpCode,
                    'body' => $response
                ]);
                throw new \Exception('API request failed with status: ' . $httpCode);
            }

        } catch (\Exception $e) {
            Log::error('JpesaService: makeXmlRequest failed', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl
            ]);
            throw $e;
        }
    }

    /**
     * Format phone number for JPesa API
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Ensure it starts with country code
        if (strpos($phoneNumber, '256') !== 0) {
            if (strpos($phoneNumber, '0') === 0) {
                $phoneNumber = '256' . substr($phoneNumber, 1);
            } else {
                $phoneNumber = '256' . $phoneNumber;
            }
        }
        
        return $phoneNumber;
    }

    /**
     * Build callback URL for JPesa
     */
    protected function buildCallbackUrl($additionalParams = [])
    {
        $baseUrl = config('services.jpesa.callback_url', route('payment.jpesa.callback'));
        
        // Add additional parameters if provided
        if (!empty($additionalParams['callback_params'])) {
            $baseUrl .= '?' . http_build_query($additionalParams['callback_params']);
        }
        
        return $baseUrl;
    }

    /**
     * Build payment description
     */
    protected function buildPaymentDescription(Transaction $transaction, $additionalParams = [])
    {
        $description = 'WiFi Payment - Transaction #' . $transaction->transaction_id;
        
        if (isset($additionalParams['description'])) {
            $description = $additionalParams['description'];
        } elseif ($transaction->package) {
            $description = 'WiFi Package: ' . $transaction->package->name . ' - Transaction #' . $transaction->transaction_id;
        }
        
        return $description;
    }

    /**
     * Validate payment parameters
     */
    protected function validatePaymentParameters(Transaction $transaction, $phoneNumber)
    {
        if (empty($this->apiKey)) {
            throw new \Exception('JPesa API key is not configured');
        }

        if (empty($phoneNumber)) {
            throw new \Exception('Phone number is required');
        }

        if ($transaction->amount <= 0) {
            throw new \Exception('Amount must be greater than 0');
        }

        if (empty($transaction->transaction_id)) {
            throw new \Exception('Transaction ID is required');
        }

        Log::info('JpesaService: Payment parameters validation passed', [
            'transaction_id' => $transaction->transaction_id,
            'amount' => $transaction->amount,
            'phone_number' => $phoneNumber
        ]);
    }

    /**
     * Simulate payment for development/testing
     */
    protected function simulatePayment(Transaction $transaction, $phoneNumber)
    {
        Log::info('JpesaService: Simulating payment', [
            'transaction_id' => $transaction->transaction_id,
            'amount' => $transaction->amount,
            'phone_number' => $phoneNumber
        ]);

        // Simulate successful payment initiation
        $simulatedTid = 'SIM_' . $transaction->transaction_id . '_' . time();
        $simulatedMemo = 'MEMO_' . $transaction->transaction_id . '_' . time();

        // Update transaction with simulated details
        $transaction->update([
            'status' => 'pending',
            'payment_details' => array_merge($transaction->payment_details ?? [], [
                'jpesa_tid' => $simulatedTid,
                'jpesa_memo' => $simulatedMemo,
                'jpesa_message' => 'Simulated payment initiated',
                'simulated' => true,
                'initiated_at' => now()->toISOString()
            ])
        ]);

        return [
            'success' => true,
            'message' => 'Simulated payment initiated successfully',
            'data' => [
                'transaction_id' => $transaction->transaction_id,
                'jpesa_tid' => $simulatedTid,
                'jpesa_memo' => $simulatedMemo,
                'status' => 'pending',
                'simulated' => true
            ]
        ];
    }

    /**
     * Verify transaction status
     */
    public function verifyTransaction($transactionId, $jpesaTid = null)
    {
        Log::info('JpesaService: Transaction verification started', [
            'transaction_id' => $transactionId,
            'jpesa_tid' => $jpesaTid
        ]);

        try {
            // For now, we'll rely on callback notifications
            // In the future, you might want to implement a status check API if JPesa provides one
            
            Log::info('JpesaService: Transaction verification completed', [
                'transaction_id' => $transactionId,
                'method' => 'callback_reliant'
            ]);

            return [
                'success' => true,
                'message' => 'Transaction verification relies on callback notifications',
                'data' => [
                    'transaction_id' => $transactionId,
                    'verification_method' => 'callback'
                ]
            ];

        } catch (\Exception $e) {
            Log::error('JpesaService: Transaction verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Transaction verification failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Generate unique transaction ID
     */
    public static function generateTransactionId()
    {
        return 'JPESA_' . time() . '_' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    /**
     * Handle callback from JPesa
     */
    public function handleCallback($callbackData)
    {
        Log::info('JpesaService: Callback received', [
            'callback_data' => $callbackData,
            'timestamp' => now()->toISOString(),
            'ip_address' => request()->ip() ?? 'unknown'
        ]);

        try {
            // Extract transaction details from callback
            $transactionId = $callbackData['tx'] ?? null;
            $jpesaTid = $callbackData['tid'] ?? null;
            // Check both 'api_status' and 'status' fields for backward compatibility
            $status = $callbackData['api_status'] ?? $callbackData['status'] ?? 'unknown';
            $message = $callbackData['msg'] ?? '';
            $memo = $callbackData['memo'] ?? null;
            $apiLog = $callbackData['_api_log_'] ?? null;

            Log::info('JpesaService: Callback status extracted', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'api_status' => $callbackData['api_status'] ?? 'not_set',
                'status_field' => $callbackData['status'] ?? 'not_set',
                'callback_keys' => array_keys($callbackData)
            ]);

            // Validate required callback data
            if (!$transactionId) {
                Log::error('JpesaService: No transaction ID in callback', [
                    'callback_data' => $callbackData,
                    'required_fields' => ['tx', 'tid', 'api_status']
                ]);
                return false;
            }

            // Find the transaction by transaction_id or jpesa_tid
            $transaction = Transaction::where('transaction_id', $transactionId)
                ->orWhereJsonContains('payment_details->jpesa_tid', $jpesaTid)
                ->first();

            if (!$transaction) {
                Log::error('JpesaService: Transaction not found for callback', [
                    'transaction_id' => $transactionId,
                    'jpesa_tid' => $jpesaTid,
                    'callback_data' => $callbackData
                ]);
                return false;
            }

            // Check if callback was already processed (prevent duplicate processing)
            $existingCallback = $transaction->payment_details['callback_received_at'] ?? null;
            if ($existingCallback) {
                Log::warning('JpesaService: Callback already processed for transaction', [
                    'transaction_id' => $transactionId,
                    'jpesa_tid' => $jpesaTid,
                    'existing_callback_time' => $existingCallback,
                    'current_callback_data' => $callbackData
                ]);
                return true; // Return true to acknowledge receipt
            }

            // Prepare update data
            $updateData = [
                'payment_details' => array_merge($transaction->payment_details ?? [], [
                    'jpesa_callback_tid' => $jpesaTid,
                    'jpesa_callback_memo' => $memo,
                    'jpesa_callback_message' => $message,
                    'jpesa_callback_api_log' => $apiLog,
                    'jpesa_callback_status' => $status,
                    'callback_received_at' => now()->toISOString(),
                    'callback_data' => $callbackData
                ])
            ];

            // Handle different callback statuses
            // JPesa uses different status values: 'success', 'approved', 'closed' for successful payments
            if ($status === 'success' || $status === 'approved' || $status === 'closed') {
                if ($transaction->status !== 'completed') {
                    $updateData['status'] = 'completed';
                    $updateData['paid_at'] = now();
                    
                    // Handle voucher payment (subscription payments removed)
                    $this->handleVoucherPayment($transaction);
                } else {
                    Log::info('JpesaService: Payment already completed, updating callback data only', [
                        'transaction_id' => $transactionId,
                        'jpesa_tid' => $jpesaTid
                    ]);
                }
                
                Log::info('JpesaService: Payment completed via callback', [
                    'transaction_id' => $transactionId,
                    'jpesa_tid' => $jpesaTid,
                    'amount' => $transaction->amount,
                    'phone_number' => $transaction->phone_number
                ]);
                
                // Set session for success page redirect
                session([
                    'last_transaction_id' => $transactionId,
                    'payment_completed' => true
                ]);
            } else {
                // Handle failed or other statuses
                $updateData['status'] = 'failed';
                $updateData['failed_at'] = now();
                
                Log::warning('JpesaService: Payment failed via callback', [
                    'transaction_id' => $transactionId,
                    'jpesa_tid' => $jpesaTid,
                    'status' => $status,
                    'message' => $message
                ]);
            }

            // Update the transaction
            $transaction->update($updateData);

            Log::info('JpesaService: Callback processed successfully', [
                'transaction_id' => $transactionId,
                'jpesa_tid' => $jpesaTid,
                'new_status' => $updateData['status'] ?? $transaction->status,
                'callback_status' => $status
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('JpesaService: Callback handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'callback_data' => $callbackData
            ]);
            return false;
        }
    }


    /**
     * Handle voucher payment completion
     */
    private function handleVoucherPayment($transaction)
    {
        Log::info('JpesaService: Processing voucher payment', [
            'transaction_id' => $transaction->transaction_id,
            'voucher_id' => $transaction->voucher_id
        ]);

        // Update tenant wallet balance
        $tenant = $transaction->tenant;
        if ($tenant) {
            $tenant->wallet_balance += $transaction->net_amount;
            $tenant->save();
            
            Log::info('JpesaService: Tenant wallet updated', [
                'tenant_id' => $tenant->id,
                'amount_added' => $transaction->net_amount,
                'new_balance' => $tenant->wallet_balance
            ]);
        }

        // Send SMS with voucher code using deduplication service
        $voucher = $transaction->voucher;
        if ($voucher && $voucher->status === 'unused') {
            try {
                $deduplicationService = new \App\Services\VoucherDeduplicationService();
                $smsResult = $deduplicationService->sendVoucherSms($transaction);
                
                if ($smsResult['success']) {
                    Log::info('JpesaService: Voucher SMS processed with deduplication', [
                        'transaction_id' => $transaction->transaction_id,
                        'voucher_code' => $smsResult['voucher_code'] ?? $voucher->code,
                        'phone_number' => $transaction->phone_number,
                        'package_name' => $smsResult['package_name'] ?? 'Unknown',
                        'duplicate' => $smsResult['duplicate'] ?? false
                    ]);
                } else {
                    Log::error('JpesaService: Failed to send voucher SMS with deduplication', [
                        'transaction_id' => $transaction->transaction_id,
                        'voucher_code' => $voucher->code,
                        'error' => $smsResult['message'] ?? 'Unknown SMS error',
                        'sms_attempts' => $smsResult['sms_attempts'] ?? 0
                    ]);
                }
            } catch (\Exception $smsException) {
                Log::error('JpesaService: Exception during SMS sending with deduplication', [
                    'transaction_id' => $transaction->transaction_id,
                    'voucher_code' => $voucher->code,
                    'error' => $smsException->getMessage(),
                    'trace' => $smsException->getTraceAsString()
                ]);
            }
        } else {
            Log::warning('JpesaService: Voucher not found or already used', [
                'transaction_id' => $transaction->transaction_id,
                'voucher_id' => $transaction->voucher_id,
                'voucher_status' => $voucher->status ?? 'not_found'
            ]);
        }
    }

    /**
     * Check transaction status using JPesa API
     * 
     * This method queries the JPesa API to get the current status
     * of a transaction when IPN is not received or delayed.
     * 
     * @param string $transactionId - JPesa transaction ID
     * @return array
     */
    public function checkTransactionStatus(string $transactionId): array
    {
        try {
            Log::info('JpesaService: Checking transaction status', [
                'transaction_id' => $transactionId,
                'api_key' => $this->apiKey ? 'SET' : 'NOT SET'
            ]);

            // Validate API key
            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'JPesa API key not configured',
                    'error_code' => 'MISSING_API_KEY'
                ];
            }

            // Prepare XML request data using correct JPesa API format
            $xmlData = '<?xml version="1.0" encoding="ISO-8859-1"?>
                <g7bill>
                    <_key_>' . $this->apiKey . '</_key_>
                    <cmd>account</cmd>
                    <action>info</action>
                    <tid>' . $transactionId . '</tid>
                </g7bill>';

            Log::info('JpesaService: Sending status check request', [
                'transaction_id' => $transactionId,
                'xml_data' => $xmlData
            ]);

            // Initialize cURL
            $ch = curl_init();
            
            // Set cURL options
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            curl_close($ch);

            // Handle cURL errors
            if ($curlError) {
                Log::error('JpesaService: cURL error during status check', [
                    'transaction_id' => $transactionId,
                    'curl_error' => $curlError,
                    'http_code' => $httpCode
                ]);

                return [
                    'success' => false,
                    'message' => 'Network error: ' . $curlError,
                    'error_code' => 'CURL_ERROR',
                    'http_code' => $httpCode
                ];
            }

            // Handle HTTP errors
            if ($httpCode !== 200) {
                Log::error('JpesaService: HTTP error during status check', [
                    'transaction_id' => $transactionId,
                    'http_code' => $httpCode,
                    'response' => $response
                ]);

                return [
                    'success' => false,
                    'message' => 'HTTP error: ' . $httpCode,
                    'error_code' => 'HTTP_ERROR',
                    'http_code' => $httpCode,
                    'response' => $response
                ];
            }

            // Parse response
            $responseData = json_decode($response, true);
            
            Log::info('JpesaService: Status check response received', [
                'transaction_id' => $transactionId,
                'response_data' => $responseData,
                'raw_response' => $response
            ]);

            // Validate response
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JpesaService: Invalid JSON response', [
                    'transaction_id' => $transactionId,
                    'json_error' => json_last_error_msg(),
                    'raw_response' => $response
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid response format',
                    'error_code' => 'INVALID_JSON',
                    'raw_response' => $response
                ];
            }

            // Check if response indicates success based on JPesa API format
            if (isset($responseData['api_status']) && $responseData['api_status'] === 'success') {
                // Extract transaction status from JPesa response
                $jpesaStatus = $responseData['status'] ?? 'unknown';
                $amount = $responseData['amount'] ?? null;
                $currency = $responseData['cur'] ?? null;
                $mobile = $responseData['mobile'] ?? null;
                $memo = $responseData['memo'] ?? null;
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'transaction_id' => $responseData['tid'] ?? $transactionId,
                    'status' => $jpesaStatus,
                    'amount' => $amount,
                    'currency' => $currency,
                    'mobile' => $mobile,
                    'memo' => $memo,
                    'message' => 'Transaction status retrieved successfully'
                ];
            } else {
                // Handle error response
                $errorMessage = $responseData['msg'] ?? 'Transaction status check failed';
                $apiStatus = $responseData['api_status'] ?? 'error';
                
                // Check if transaction doesn't exist (should be marked as failed)
                $shouldMarkAsFailed = $this->shouldMarkTransactionAsFailed($errorMessage);
                
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'error_code' => $apiStatus,
                    'data' => $responseData,
                    'should_mark_as_failed' => $shouldMarkAsFailed
                ];
            }

        } catch (\Exception $e) {
            Log::error('JpesaService: Exception during status check', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Check and update transaction status if IPN is missing
     * 
     * This method checks if a transaction is still pending and
     * queries JPesa API to get the current status.
     * 
     * @param Transaction $transaction
     * @return array
     */
    public function checkAndUpdateTransactionStatus(Transaction $transaction): array
    {
        try {
            // Only check pending transactions
            if ($transaction->status !== 'pending') {
                return [
                    'success' => true,
                    'message' => 'Transaction is not pending, no status check needed',
                    'status' => $transaction->status
                ];
            }

            // Check if transaction is older than 5 minutes (to avoid checking too early)
            if ($transaction->created_at->diffInMinutes(now()) < 5) {
                return [
                    'success' => true,
                    'message' => 'Transaction too recent, skipping status check',
                    'status' => 'pending'
                ];
            }

            Log::info('JpesaService: Checking status for pending transaction', [
                'transaction_id' => $transaction->transaction_id,
                'jpesa_reference' => $transaction->jpesa_reference,
                'created_at' => $transaction->created_at,
                'age_minutes' => $transaction->created_at->diffInMinutes(now())
            ]);

            // Use jpesa_reference if available, otherwise use transaction_id
            $jpesaTransactionId = $transaction->jpesa_reference ?: $transaction->transaction_id;

            // Check transaction status
            $statusResult = $this->checkTransactionStatus($jpesaTransactionId);

            if (!$statusResult['success']) {
                // Check if transaction should be marked as failed
                if (isset($statusResult['should_mark_as_failed']) && $statusResult['should_mark_as_failed']) {
                    Log::info('JpesaService: Marking transaction as failed due to JPesa error', [
                        'transaction_id' => $transaction->transaction_id,
                        'error_message' => $statusResult['message'],
                        'error_code' => $statusResult['error_code'] ?? 'UNKNOWN_ERROR'
                    ]);

                    // Mark transaction as failed
                    $transaction->update([
                        'status' => 'failed',
                        'payment_details' => array_merge(
                            $transaction->payment_details ?? [],
                            [
                                'status_check_error' => $statusResult['data'],
                                'status_check_at' => now()->toISOString(),
                                'failure_reason' => 'Transaction not found in JPesa',
                                'jpesa_error' => $statusResult['message']
                            ]
                        )
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Transaction marked as failed due to JPesa error',
                        'old_status' => 'pending',
                        'new_status' => 'failed',
                        'reason' => $statusResult['message']
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Failed to check transaction status: ' . $statusResult['message'],
                    'error_code' => $statusResult['error_code'] ?? 'UNKNOWN_ERROR'
                ];
            }

            // Map JPesa status to local status
            $jpesaStatus = $statusResult['status'] ?? 'unknown';
            $localStatus = $this->mapStatusToLocal($jpesaStatus);

            Log::info('JpesaService: Status check completed', [
                'transaction_id' => $transaction->transaction_id,
                'jpesa_status' => $jpesaStatus,
                'local_status' => $localStatus,
                'status_data' => $statusResult['data'] ?? null
            ]);

            // Update transaction if status changed
            if ($localStatus !== $transaction->status) {
                $transaction->update([
                    'status' => $localStatus,
                    'payment_details' => array_merge(
                        $transaction->payment_details ?? [],
                        [
                            'status_check' => $statusResult['data'],
                            'status_check_at' => now()->toISOString(),
                            'jpesa_status' => $jpesaStatus
                        ]
                    )
                ]);

                // If transaction is now completed, process it
                if ($localStatus === 'completed') {
                    $this->handleVoucherPayment($transaction);
                }

                return [
                    'success' => true,
                    'message' => 'Transaction status updated',
                    'old_status' => $transaction->status,
                    'new_status' => $localStatus,
                    'jpesa_status' => $jpesaStatus
                ];
            }

            return [
                'success' => true,
                'message' => 'Transaction status unchanged',
                'status' => $localStatus,
                'jpesa_status' => $jpesaStatus
            ];

        } catch (\Exception $e) {
            Log::error('JpesaService: Exception during status check and update', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Exception occurred: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Determine if a transaction should be marked as failed based on error message
     * 
     * @param string $errorMessage
     * @return bool
     */
    private function shouldMarkTransactionAsFailed(string $errorMessage): bool
    {
        $failureIndicators = [
            'transaction not found',
            'invalid transaction',
            'transaction does not exist',
            'transaction expired',
            'transaction cancelled',
            'transaction failed',
            'invalid transaction id',
            'transaction id not found'
        ];

        $errorMessageLower = strtolower($errorMessage);
        
        foreach ($failureIndicators as $indicator) {
            if (strpos($errorMessageLower, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map JPesa status to local transaction status
     * 
     * Based on JPesa API documentation:
     * - "closed" = Transaction completed successfully
     * - "open" = Transaction pending/processing
     * - "failed" = Transaction failed
     * 
     * @param string $jpesaStatus
     * @return string
     */
    private function mapStatusToLocal(string $jpesaStatus): string
    {
        $statusMap = [
            'closed' => 'completed',      // Transaction completed successfully
            'open' => 'pending',          // Transaction pending/processing
            'failed' => 'failed',        // Transaction failed
            'cancelled' => 'failed',     // Transaction cancelled
            'expired' => 'failed',       // Transaction expired
            'success' => 'completed',    // Legacy success status
            'completed' => 'completed',  // Legacy completed status
            'paid' => 'completed',       // Legacy paid status
            'confirmed' => 'completed',  // Legacy confirmed status
            'pending' => 'pending',      // Legacy pending status
            'processing' => 'pending',   // Legacy processing status
            'unknown' => 'pending'       // Unknown status defaults to pending
        ];

        return $statusMap[strtolower($jpesaStatus)] ?? 'pending';
    }
}
