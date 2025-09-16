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
            $status = $callbackData['api_status'] ?? 'unknown';
            $message = $callbackData['msg'] ?? '';
            $memo = $callbackData['memo'] ?? null;
            $apiLog = $callbackData['_api_log_'] ?? null;

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
            if ($status === 'success') {
                if ($transaction->status !== 'completed') {
                    $updateData['status'] = 'completed';
                    $updateData['paid_at'] = now();
                    
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

                    // Send SMS with voucher code and mark voucher as used
                    $voucher = $transaction->voucher;
                    if ($voucher && $voucher->status === 'unused') {
                        try {
                            $smsService = new \App\Services\UgSmsService();
                            $smsResult = $smsService->sendVoucherCode($transaction->phone_number, $voucher->code, $voucher->package);
                            
                            if ($smsResult['success']) {
                                // Mark voucher as used
                                $voucher->update([
                                    'status' => 'used',
                                    'used_at' => now(),
                                    'phone_number' => $transaction->phone_number,
                                ]);
                                
                                Log::info('JpesaService: Voucher SMS sent and voucher marked as used', [
                                    'transaction_id' => $transactionId,
                                    'voucher_code' => $voucher->code,
                                    'phone_number' => $transaction->phone_number,
                                    'package_name' => $voucher->package->name ?? 'Unknown'
                                ]);
                            } else {
                                Log::error('JpesaService: Failed to send voucher SMS', [
                                    'transaction_id' => $transactionId,
                                    'voucher_code' => $voucher->code,
                                    'error' => $smsResult['message'] ?? 'Unknown SMS error'
                                ]);
                            }
                        } catch (\Exception $smsException) {
                            Log::error('JpesaService: Exception during SMS sending', [
                                'transaction_id' => $transactionId,
                                'voucher_code' => $voucher->code,
                                'error' => $smsException->getMessage(),
                                'trace' => $smsException->getTraceAsString()
                            ]);
                        }
                    } else {
                        Log::warning('JpesaService: Voucher not found or already used', [
                            'transaction_id' => $transactionId,
                            'voucher_id' => $transaction->voucher_id,
                            'voucher_status' => $voucher->status ?? 'not_found'
                        ]);
                    }
                    
                    Log::info('JpesaService: Payment completed via callback', [
                        'transaction_id' => $transactionId,
                        'jpesa_tid' => $jpesaTid,
                        'amount' => $transaction->amount,
                        'phone_number' => $transaction->phone_number
                    ]);
                    
                    // Set session for success page redirect
                    session(['last_transaction_id' => $transactionId]);
                } else {
                    Log::info('JpesaService: Payment already completed, updating callback data only', [
                        'transaction_id' => $transactionId,
                        'jpesa_tid' => $jpesaTid
                    ]);
                }
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
}
