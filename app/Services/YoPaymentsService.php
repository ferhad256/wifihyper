<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoPaymentsService
{
    protected $baseUrl;
    protected $fallbackUrl;
    protected $username;
    protected $password;
    protected $privateKeyPath;
    protected $publicKeyEnabled;

    public function __construct()
    {
        // Debug configuration loading
        $username = config('services.yo_payments.username');
        $password = config('services.yo_payments.password');
        
        Log::info('YoPaymentsService: Configuration loaded', [
            'username' => $username ? 'SET' : 'NOT SET',
            'password' => $password ? 'SET' : 'NOT SET',
            'base_url' => config('services.yo_payments.base_url'),
            'fallback_url' => config('services.yo_payments.fallback_url'),
        ]);
        
        $this->baseUrl = config('services.yo_payments.base_url', 'https://paymentsapi1.yo.co.ug/ybs/task.php');
        $this->fallbackUrl = config('services.yo_payments.fallback_url', 'https://paymentsapi2.yo.co.ug/ybs/task.php');
        $this->username = $username;
        $this->password = $password;
        $this->privateKeyPath = config('services.yo_payments.private_key_path');
        $this->publicKeyEnabled = config('services.yo_payments.public_key_enabled', false);
        
        // Validate required credentials
        if (empty($this->username) || empty($this->password)) {
            Log::error('YoPaymentsService: Missing required credentials', [
                'username_set' => !empty($this->username),
                'password_set' => !empty($this->password),
                'config_services' => config('services'),
            ]);
        }
    }

    /**
     * Initialize a payment transaction using Yo Payments API 6.1 PULL METHOD
     * 
     * This method implements the official Yo Payments API specification for
     * initiating pull-based deposits from mobile money accounts.
     * 
     * @param Transaction $transaction - Transaction model instance
     * @param string $phoneNumber - Customer phone number
     * @param array $additionalParams - Optional additional parameters
     * @return array
     */
    public function initiatePayment(Transaction $transaction, $phoneNumber, $additionalParams = [])
    {
        Log::info('YoPaymentsService: Payment initiation started (API 6.1 PULL METHOD)', [
            'transaction_id' => $transaction->transaction_id,
            'amount' => $transaction->amount,
            'phone_number' => $phoneNumber,
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'timestamp' => now()->toISOString()
        ]);

        // Check if we're in test mode (for development)
        if (config('app.env') === 'local' && config('app.debug') === true) {
            Log::info('YoPaymentsService: Using development mode - simulating payment', [
                'transaction_id' => $transaction->transaction_id
            ]);
            return $this->simulatePayment($transaction, $phoneNumber);
        }

        try {
            // Validate mandatory parameters as per API specification
            $this->validatePaymentParameters($transaction, $phoneNumber);

            Log::info('YoPaymentsService: Building payment parameters (API 6.1 compliant)', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'phone_number' => $phoneNumber
            ]);

            // Build base parameters according to API 6.1 specification
            $parameters = [
                'NonBlocking' => 'TRUE', // Optional: Use non-blocking for better performance
                'Amount' => $transaction->amount, // Mandatory: Amount to be deducted
                'Account' => $this->formatPhoneNumberForDeposit($phoneNumber), // Mandatory: Mobile money account number
                'AccountProviderCode' => $this->getProviderCode($additionalParams['provider_code'] ?? 'MTN'), // Optional: Mobile provider code
                'Narrative' => $this->buildPaymentNarrative($transaction, $additionalParams), // Mandatory: Transaction description
                'PrivateTransactionReference' => $transaction->transaction_id, // Use PrivateTransactionReference instead of ExternalReference
                'InstantNotificationUrl' => $this->buildUnifiedNotificationUrl($additionalParams), // Unified IPN URL for all responses
                'FailureNotificationUrl' => $this->buildUnifiedNotificationUrl($additionalParams), // Same unified URL for failures
            ];

            // Add optional parameters if provided
            $parameters = $this->addOptionalParameters($parameters, $additionalParams);

            Log::info('YoPaymentsService: Payment parameters built (API 6.1 compliant)', [
                'transaction_id' => $transaction->transaction_id,
                'parameters' => $parameters,
                'callback_url' => $parameters['InstantNotificationUrl'],
                'failure_url' => $parameters['FailureNotificationUrl']
            ]);

            // Add authentication signature if required
            if ($this->publicKeyEnabled && $this->privateKeyPath) {
                Log::info('YoPaymentsService: Adding authentication signature', [
                    'transaction_id' => $transaction->transaction_id,
                    'public_key_enabled' => $this->publicKeyEnabled,
                    'private_key_path' => $this->privateKeyPath
                ]);
                
                $signature = $this->generateDepositSignature($parameters);
                $parameters['AuthenticationSignatureBase64'] = $signature;
                
                Log::info('YoPaymentsService: Authentication signature generated', [
                    'transaction_id' => $transaction->transaction_id,
                    'signature_length' => strlen($signature)
                ]);
            } else {
                Log::info('YoPaymentsService: No authentication signature required', [
                    'transaction_id' => $transaction->transaction_id,
                    'public_key_enabled' => $this->publicKeyEnabled
                ]);
            }

            Log::info('YoPaymentsService: Building XML request', [
                'transaction_id' => $transaction->transaction_id,
                'method' => 'acdepositfunds'
            ]);

            $xmlRequest = $this->buildXmlRequest('acdepositfunds', $parameters);

            Log::info('YoPaymentsService: XML request built', [
                'transaction_id' => $transaction->transaction_id,
                'xml_length' => strlen($xmlRequest),
                'xml_preview' => substr($xmlRequest, 0, 200) . '...'
            ]);

            Log::info('YoPaymentsService: Making XML request to Yo Payments API', [
                'transaction_id' => $transaction->transaction_id,
                'base_url' => $this->baseUrl
            ]);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('YoPaymentsService: Yo Payments API response received', [
                'transaction_id' => $transaction->transaction_id,
                'phone_number' => $phoneNumber,
                'public_key_enabled' => $this->publicKeyEnabled,
                'response_success' => $response['success'] ?? false,
                'response_message' => $response['message'] ?? 'No message',
                'response_data' => $response['data'] ?? null,
                'full_response' => $response
            ]);

            if ($response['success']) {
                Log::info('YoPaymentsService: Payment initiated successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'yo_payments_data' => $response['data'],
                    'transaction_reference' => $response['transaction_reference'] ?? null
                ]);

                // Update transaction with payment details
                $transaction->update([
                    'payment_details' => $response['data'],
                    'status' => 'pending',
                ]);

                Log::info('YoPaymentsService: Transaction updated with payment details', [
                    'transaction_id' => $transaction->transaction_id,
                    'status' => 'pending'
                ]);

                return [
                    'success' => true,
                    'data' => $response['data'],
                    'transaction_reference' => $response['transaction_reference'] ?? null,
                    'message' => 'Payment initiated successfully',
                ];
            }

            Log::error('YoPaymentsService: Payment initiation failed', [
                'transaction_id' => $transaction->transaction_id,
                'yo_payments_error' => $response['message'] ?? 'Unknown error',
                'yo_payments_response' => $response
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('YoPaymentsService: Payment initiation exception', [
                'transaction_id' => $transaction->transaction_id,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return [
                'success' => false,
                'message' => 'Payment service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Simulate payment for development/testing
     */
    protected function simulatePayment(Transaction $transaction, $phoneNumber)
    {
        Log::info('Simulating Yo Payments payment for development', [
            'transaction_id' => $transaction->transaction_id,
            'phone_number' => $phoneNumber,
            'amount' => $transaction->amount,
        ]);

        // Simulate a successful payment response
        $simulatedResponse = [
            'success' => true,
            'data' => [
                'Response' => [
                    'Status' => 'OK',
                    'StatusCode' => '0',
                    'TransactionStatus' => 'SUCCEEDED',
                    'TransactionReference' => 'SIM_' . $transaction->transaction_id,
                    'StatusMessage' => 'Payment simulated successfully for development',
                ]
            ],
            'transaction_reference' => 'SIM_' . $transaction->transaction_id,
            'message' => 'Payment simulated successfully (Development Mode)',
        ];

        // Update transaction as completed for testing
        $transaction->update([
            'status' => 'completed',
            'paid_at' => now(),
            'payment_details' => array_merge($simulatedResponse['data'], [
                'yo_payments_reference' => 'SIM_' . $transaction->transaction_id,
                'simulation_mode' => true,
                'simulated_at' => now()->toISOString(),
            ]),
        ]);

        // Mark voucher as used in simulation
        if ($transaction->voucher && $transaction->voucher->status === 'unused') {
            $transaction->voucher->update([
                'status' => 'used',
                'used_at' => now(),
                'phone_number' => $phoneNumber,
            ]);
            
            Log::info('Voucher marked as used in simulation', [
                'transaction_id' => $transaction->transaction_id,
                'voucher_id' => $transaction->voucher->id,
                'voucher_code' => $transaction->voucher->code,
                'package_name' => $transaction->package->name ?? 'Unknown',
            ]);
        }

        // Update tenant wallet balance in simulation
        $tenant = $transaction->tenant;
        $tenant->wallet_balance += $transaction->net_amount;
        $tenant->save();

        // Send SMS with voucher code (simulated payment)
        try {
            $smsService = new UgSmsService();
            $smsResult = $smsService->sendVoucherCode(
                $phoneNumber,
                $transaction->voucher->code,
                $transaction->package
            );

            if (!$smsResult['success']) {
                Log::error('Failed to send voucher SMS in simulation', [
                    'transaction_id' => $transaction->transaction_id,
                    'sms_error' => $smsResult['message'],
                ]);
            } else {
                Log::info('Voucher SMS sent successfully in simulation', [
                    'transaction_id' => $transaction->transaction_id,
                    'phone_number' => $phoneNumber,
                    'voucher_code' => $transaction->voucher->code,
                    'package_name' => $transaction->package->name ?? 'Unknown',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SMS sending error in simulation', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $simulatedResponse;
    }

    /**
     * Process payment callback
     */
    public function processCallback($data)
    {
        try {
            $transactionId = '';
            $status = '';
            $amount = '';
            $currency = 'UGX';
            
            // Check if data is XML or form-encoded
            if (strpos($data, '<?xml') === 0 || strpos($data, '<') === 0) {
                // Parse XML callback data
                $xml = simplexml_load_string($data);
                
                if (!$xml) {
                    Log::error('Yo Payments Callback Error: Invalid XML', ['data' => $data]);
                    return ['success' => false, 'message' => 'Invalid callback data'];
                }
                
                $transactionId = (string) $xml->TransactionReference;
                $status = (string) $xml->Status;
                $amount = (string) $xml->Amount;
                $currency = (string) $xml->Currency;
                
                Log::info('Yo Payments Callback: XML format processed', [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'amount' => $amount,
                    'currency' => $currency,
                ]);
            } else {
                // Parse form-encoded data
                parse_str($data, $formData);
                
                // Map Yo Payments actual field names to our expected format
                $transactionId = $formData['TransactionReference'] ?? $formData['external_ref'] ?? '';
                $status = $formData['Status'] ?? 'OK'; // Default to OK if no status field
                $amount = $formData['amount'] ?? $formData['Amount'] ?? '';
                $currency = $formData['Currency'] ?? 'UGX';
                
                // If we have TransactionReference, this is a successful payment
                if ($formData['TransactionReference'] && !$status) {
                    $status = 'OK';
                }
                
                Log::info('Yo Payments Callback: Form data processed', [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'amount' => $amount,
                    'currency' => $currency,
                    'form_data' => $formData,
                ]);
            }
            
            // Validate required fields
            if (!$transactionId || !$status) {
                Log::error('Yo Payments Callback Error: Missing required fields', [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'data' => $data
                ]);
                return ['success' => false, 'message' => 'Missing required fields'];
            }

            // Find transaction
            $transaction = Transaction::where('transaction_id', $transactionId)->first();
            
            if (!$transaction) {
                Log::error('Yo Payments Callback Error: Transaction not found', ['transaction_id' => $transactionId]);
                return ['success' => false, 'message' => 'Transaction not found'];
            }

            // Update transaction status
            $transaction->update([
                'status' => $this->mapPaymentStatus($status),
                'payment_details' => [
                    'callback_status' => $status,
                    'callback_amount' => $amount,
                    'callback_currency' => $currency,
                    'callback_received_at' => now(),
                ],
            ]);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => $status,
                'message' => 'Callback processed successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Callback Error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => 'Callback processing error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process withdrawal request
     */
    public function initiateWithdrawal($amount, $phoneNumber, $narrative = 'WIFIHYPER Withdrawal', $externalReference = null)
    {
        try {
            $parameters = [
                'NonBlocking' => 'TRUE', // Use non-blocking for better performance
                'Amount' => $amount,
                'Account' => $this->formatPhoneNumberForWithdrawal($phoneNumber),
                'AccountProviderCode' => $this->getProviderCode('MTN'), // Default to MTN, can be made configurable
                'Narrative' => $narrative,
                'ExternalReference' => $externalReference ?? 'WITHDRAW_' . time() . '_' . rand(1000, 9999),
            ];

            // Add public key authentication if enabled
            if ($this->publicKeyEnabled && $this->privateKeyPath) {
                $nonce = $this->generateNonce();
                $signature = $this->generateSignature($parameters, $nonce);
                
                $parameters['PublicKeyAuthenticationNonce'] = $nonce;
                $parameters['PublicKeyAuthenticationSignatureBase64'] = $signature;
            }

            $xmlRequest = $this->buildXmlRequest('acwithdrawfunds', $parameters);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments Withdrawal Response', [
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'public_key_enabled' => $this->publicKeyEnabled,
                'response' => $response,
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'],
                    'transaction_reference' => $response['transaction_reference'] ?? null,
                    'message' => 'Withdrawal initiated successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to initiate withdrawal: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Withdrawal Error', [
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Withdrawal service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build XML request compliant with Yo Payments API specification
     * 
     * Format: <?xml version="1.0" encoding="UTF-8"?>
     *         <AutoCreate>
     *           <Request>
     *             <APIUsername></APIUsername>
     *             <APIPassword></APIPassword>
     *             <Method></Method>
     *             [Additional parameters...]
     *           </Request>
     *         </AutoCreate>
     */
    protected function buildXmlRequest($method, $parameters = [])
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<AutoCreate>' . "\n";
        $xml .= ' <Request>' . "\n";
        $xml .= '  <APIUsername>' . htmlspecialchars($this->username) . '</APIUsername>' . "\n";
        $xml .= '  <APIPassword>' . htmlspecialchars($this->password) . '</APIPassword>' . "\n";
        $xml .= '  <Method>' . htmlspecialchars($method) . '</Method>' . "\n";
        
        foreach ($parameters as $key => $value) {
            $xml .= '  <' . htmlspecialchars($key) . '>' . htmlspecialchars($value) . '</' . htmlspecialchars($key) . '>' . "\n";
        }
        
        $xml .= ' </Request>' . "\n";
        $xml .= '</AutoCreate>';

        return $xml;
    }

    /**
     * Make XML request to Yo Payments API
     */
    protected function makeXmlRequest($xmlRequest)
    {
        Log::info('YoPaymentsService: makeXmlRequest started', [
            'xml_length' => strlen($xmlRequest),
            'primary_url' => $this->baseUrl,
            'fallback_url' => $this->fallbackUrl,
            'timestamp' => now()->toISOString()
        ]);

        // Try primary URL first
        Log::info('YoPaymentsService: Attempting primary URL', [
            'url' => $this->baseUrl
        ]);
        
        $result = $this->makeRequest($this->baseUrl, $xmlRequest);
        
        Log::info('YoPaymentsService: Primary URL response', [
            'result' => $result,
            'result_type' => gettype($result),
            'result_length' => is_string($result) ? strlen($result) : 'N/A'
        ]);
        
        // If primary URL fails, try fallback URL
        if ($result === FALSE) {
            Log::warning('YoPaymentsService: Primary URL failed, trying fallback URL', [
                'primary_url' => $this->baseUrl,
                'fallback_url' => $this->fallbackUrl,
            ]);
            
            $result = $this->makeRequest($this->fallbackUrl, $xmlRequest);
            
            Log::info('YoPaymentsService: Fallback URL response', [
                'result' => $result,
                'result_type' => gettype($result),
                'result_length' => is_string($result) ? strlen($result) : 'N/A'
            ]);
        }

        if ($result === FALSE) {
            Log::error('YoPaymentsService: Both primary and fallback URLs failed', [
                'primary_url' => $this->baseUrl,
                'fallback_url' => $this->fallbackUrl
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to connect to Yo Payments API (both primary and fallback URLs failed)'
            ];
        }

        Log::info('YoPaymentsService: Parsing XML response', [
            'response_length' => strlen($result),
            'response_preview' => substr($result, 0, 200) . '...'
        ]);

        // Parse XML response
        $xml = simplexml_load_string($result);
        
        if (!$xml) {
            Log::error('YoPaymentsService: Failed to parse XML response', [
                'raw_response' => $result,
                'xml_errors' => libxml_get_errors()
            ]);
            
            return [
                'success' => false,
                'message' => 'Invalid response from Yo Payments API'
            ];
        }

        Log::info('YoPaymentsService: XML parsed successfully', [
            'xml_object' => $xml,
            'xml_children' => array_keys((array)$xml)
        ]);

        $response = [
            'success' => true,
            'data' => $this->xmlToArray($xml),
            'raw_response' => $result
        ];

        // Check for API response based on the documented response format
        if (isset($xml->Response)) {
            $status = (string) $xml->Response->Status;
            $statusCode = (string) $xml->Response->StatusCode;
            $statusMessage = (string) $xml->Response->StatusMessage;
            $errorMessageCode = (string) $xml->Response->ErrorMessageCode;
            $errorMessage = (string) $xml->Response->ErrorMessage;
            $transactionStatus = (string) $xml->Response->TransactionStatus;
            $transactionReference = (string) $xml->Response->TransactionReference;
            
            Log::info('YoPaymentsService: API response details', [
                'status' => $status,
                'status_code' => $statusCode,
                'status_message' => $statusMessage,
                'error_message_code' => $errorMessageCode,
                'error_message' => $errorMessage,
                'transaction_status' => $transactionStatus,
                'transaction_reference' => $transactionReference
            ]);
            
            // Check if request was successful or pending
            if ($status === 'OK' && ($statusCode === '0' || $statusCode === '1')) {
                // Request was successful or pending
                $response['transaction_status'] = $transactionStatus;
                $response['transaction_reference'] = $transactionReference;
                $response['status_message'] = $statusMessage;
                
                // If status code is 1, it's pending
                if ($statusCode === '1') {
                    $response['is_pending'] = true;
                    $response['message'] = 'Payment is pending confirmation';
                    
                    Log::info('YoPaymentsService: Payment is pending', [
                        'transaction_reference' => $transactionReference,
                        'status_message' => $statusMessage
                    ]);
                } else {
                    $response['is_pending'] = false;
                    $response['message'] = 'Payment processed successfully';
                    
                    Log::info('YoPaymentsService: Payment processed successfully', [
                        'transaction_reference' => $transactionReference,
                        'status_message' => $statusMessage
                    ]);
                }
            } else {
                // Request was unsuccessful
                $response['success'] = false;
                $response['status'] = $status;
                $response['status_code'] = $statusCode;
                $response['status_message'] = $statusMessage;
                $response['error_message_code'] = $errorMessageCode;
                $response['error_message'] = $errorMessage;
                $response['transaction_status'] = $transactionStatus;
                $response['transaction_reference'] = $transactionReference;
                
                // Build error message
                $errorMsg = 'API request failed';
                if ($statusMessage) {
                    $errorMsg = $statusMessage;
                }
                if ($errorMessage) {
                    $errorMsg .= ' - ' . $errorMessage;
                }
                $response['message'] = $errorMsg;
                
                // Log detailed error information
                Log::error('YoPaymentsService: API request failed', [
                    'status' => $status,
                    'status_code' => $statusCode,
                    'status_message' => $statusMessage,
                    'error_message_code' => $errorMessageCode,
                    'error_message' => $errorMessage,
                    'transaction_status' => $transactionStatus,
                    'transaction_reference' => $transactionReference,
                    'full_response' => $response
                ]);
            }
        } else {
            Log::warning('YoPaymentsService: No Response element found in XML', [
                'xml_structure' => $this->xmlToArray($xml),
                'raw_response' => $result
            ]);
        }

        Log::info('YoPaymentsService: makeXmlRequest completed', [
            'final_response' => $response,
            'success' => $response['success'] ?? false
        ]);

        return $response;
    }

    /**
     * Make HTTP request to a specific URL
     */
    protected function makeRequest($url, $xmlRequest)
    {
        try {
            $options = [
                'http' => [
                    'header' => "Content-Type: text/xml\r\nContent-transfer-encoding: text\r\n",
                    'method' => 'POST',
                    'content' => $xmlRequest,
                    'timeout' => 30, // 30 seconds timeout
                ]
            ];

            $context = stream_context_create($options);
            return file_get_contents($url, false, $context);

        } catch (\Exception $e) {
            Log::error('Yo Payments Request Error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return FALSE;
        }
    }

    /**
     * Convert XML to array
     */
    protected function xmlToArray($xml)
    {
        $array = [];
        
        foreach ($xml->children() as $child) {
            $key = $child->getName();
            $value = (string) $child;
            
            if ($child->count() > 0) {
                $array[$key] = $this->xmlToArray($child);
            } else {
                $array[$key] = $value;
            }
        }
        
        return $array;
    }

    /**
     * Format phone number for Yo Payments
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        
        // If it starts with 256, convert to 0
        if (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '256') {
            $phoneNumber = '0' . substr($phoneNumber, 3);
        }
        
        // If it doesn't start with 0, add it
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) !== '0') {
            $phoneNumber = '0' . $phoneNumber;
        }
        
        return $phoneNumber;
    }

    /**
     * Format phone number for withdrawal (with international code)
     */
    protected function formatPhoneNumberForWithdrawal($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        
        // If it starts with 0, convert to 256
        if (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '256' . substr($phoneNumber, 1);
        }
        
        // If it doesn't start with 256, add it
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) !== '256') {
            $phoneNumber = '256' . $phoneNumber;
        }
        
        // Ensure it's 12 digits (256 + 9 digits)
        if (strlen($phoneNumber) !== 12) {
            throw new \Exception('Invalid phone number format for withdrawal');
        }
        
        return $phoneNumber;
    }

    /**
     * Format phone number for deposit (with international code)
     */
    protected function formatPhoneNumberForDeposit($phoneNumber)
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        
        // If it starts with 0, convert to 256
        if (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '256' . substr($phoneNumber, 1);
        }
        
        // If it doesn't start with 256, add it
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) !== '256') {
            $phoneNumber = '256' . $phoneNumber;
        }
        
        // Ensure it's 12 digits (256 + 9 digits)
        if (strlen($phoneNumber) !== 12) {
            throw new \Exception('Invalid phone number format for deposit');
        }
        
        return $phoneNumber;
    }

    /**
     * Format phone number for balance check
     */
    protected function formatPhoneNumberForBalance($phoneNumber)
    {
        return $this->formatPhoneNumberForDeposit($phoneNumber);
    }

    /**
     * Format phone number for account validation
     */
    protected function formatPhoneNumberForValidation($phoneNumber)
    {
        return $this->formatPhoneNumberForDeposit($phoneNumber);
    }

    /**
     * Format phone number for transaction history
     */
    protected function formatPhoneNumberForHistory($phoneNumber)
    {
        return $this->formatPhoneNumberForDeposit($phoneNumber);
    }

    /**
     * Validate payment parameters according to Yo Payments API 6.1 specification
     * 
     * @param Transaction $transaction
     * @param string $phoneNumber
     * @throws \Exception
     */
    protected function validatePaymentParameters($transaction, $phoneNumber)
    {
        // Validate amount (must be greater than zero)
        if (empty($transaction->amount) || $transaction->amount <= 0) {
            throw new \Exception('Amount must be greater than zero as per API 6.1 specification');
        }

        // Validate phone number format
        $formattedPhone = $this->formatPhoneNumberForDeposit($phoneNumber);
        if (strlen($formattedPhone) !== 12 || substr($formattedPhone, 0, 3) !== '256') {
            throw new \Exception('Phone number must be in international format (256XXXXXXXXX) as per API 6.1 specification');
        }

        // Validate narrative length (maximum 4096 characters)
        $narrative = $this->buildPaymentNarrative($transaction, []);
        if (strlen($narrative) > 4096) {
            throw new \Exception('Narrative exceeds maximum length of 4096 characters as per API 6.1 specification');
        }

        Log::info('YoPaymentsService: Payment parameters validation passed', [
            'transaction_id' => $transaction->transaction_id,
            'amount' => $transaction->amount,
            'phone_number' => $formattedPhone,
            'narrative_length' => strlen($narrative)
        ]);
    }

    /**
     * Build payment narrative according to API 6.1 specification
     * 
     * @param Transaction $transaction
     * @param array $additionalParams
     * @return string
     */
    protected function buildPaymentNarrative($transaction, $additionalParams)
    {
        $baseNarrative = "WiFi Package - " . ($transaction->package->name ?? 'Unknown Package');
        
        // Add custom narrative if provided
        if (!empty($additionalParams['custom_narrative'])) {
            $baseNarrative = $additionalParams['custom_narrative'];
        }
        
        // Add hotspot information if available
        if (!empty($additionalParams['hotspot_name'])) {
            $baseNarrative .= " at " . $additionalParams['hotspot_name'];
        }
        
        // Ensure narrative doesn't exceed 4096 characters
        if (strlen($baseNarrative) > 4096) {
            $baseNarrative = substr($baseNarrative, 0, 4093) . '...';
        }
        
        return $baseNarrative;
    }

    /**
     * Build notification URLs with proper encoding as per Yo Payments API specification
     * 
     * @param string $type - 'success' or 'failure'
     * @param array $additionalParams
     * @return string
     */
    protected function buildNotificationUrl($type, $additionalParams)
    {
        // Use config values for IPN URLs with fallback to route generation
        $configKey = $type === 'success' ? 'success' : 'failure';
        $baseUrl = config("services.yo_payments.ipn_urls.{$configKey}");
        
        if (!$baseUrl) {
            // Fallback to route generation if config not set
            $baseUrl = $type === 'success' ? route('payment.callback') : route('payment.failed.post');
        }
        
        // Add custom parameters if provided
        if (!empty($additionalParams['notification_params'])) {
            $queryParams = http_build_query($additionalParams['notification_params']);
            $baseUrl .= (strpos($baseUrl, '?') === false ? '?' : '&') . $queryParams;
        }
        
        // Properly encode the URL as per Yo Payments API specification
        // Replace special XML characters with escape sequences
        $encodedUrl = str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $baseUrl
        );
        
        Log::info('YoPaymentsService: Notification URL built', [
            'type' => $type,
            'original_url' => $baseUrl,
            'encoded_url' => $encodedUrl,
            'config_key' => $configKey
        ]);
        
        return $encodedUrl;
    }

    /**
     * Add optional parameters according to API 6.1 specification
     * 
     * @param array $parameters
     * @param array $additionalParams
     * @return array
     */
    protected function addOptionalParameters($parameters, $additionalParams)
    {
        // Add internal reference if provided
        if (!empty($additionalParams['internal_reference'])) {
            $parameters['InternalReference'] = $additionalParams['internal_reference'];
        }
        
        // Add provider reference text if provided
        if (!empty($additionalParams['provider_reference_text'])) {
            $parameters['ProviderReferenceText'] = $additionalParams['provider_reference_text'];
        }
        
        // Add narrative file if provided
        if (!empty($additionalParams['narrative_file_path']) && !empty($additionalParams['narrative_file_name'])) {
            $parameters['NarrativeFileName'] = $additionalParams['narrative_file_name'];
            $parameters['NarrativeFileBase64'] = $this->encodeFileToBase64($additionalParams['narrative_file_path']);
        }
        
        return $parameters;
    }

    /**
     * Encode file to base64 for narrative file attachment
     * 
     * @param string $filePath
     * @return string
     */
    protected function encodeFileToBase64($filePath)
    {
        if (!file_exists($filePath)) {
            Log::warning('YoPaymentsService: Narrative file not found', ['file_path' => $filePath]);
            return '';
        }
        
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            Log::warning('YoPaymentsService: Failed to read narrative file', ['file_path' => $filePath]);
            return '';
        }
        
        return base64_encode($fileContent);
    }

    /**
     * Generate authentication signature for deposit requests
     */
    protected function generateDepositSignature($parameters)
    {
        try {
            // Read private key
            $privateKey = file_get_contents($this->privateKeyPath);
            if (!$privateKey) {
                throw new \Exception('Private key file not found or unreadable');
            }

            // Create private key resource
            $privateKeyResource = openssl_pkey_get_private($privateKey);
            if (!$privateKeyResource) {
                throw new \Exception('Invalid private key format');
            }

            // Get source IP address
            $sourceIp = request()->ip() ?? '127.0.0.1';

            // Concatenate parameters as per Yo Payments API 6.1 specification for deposits
            // Order: 1. APIUsername, 2. APIPassword, 3. Amount, 4. Account, 5. Narrative, 6. ExternalReference, 7. Source IP
            $concatenatedString = $this->username;
            $concatenatedString .= $this->password;
            $concatenatedString .= $parameters['Amount'];
            $concatenatedString .= $parameters['Account'];
            $concatenatedString .= $parameters['Narrative'];
            $concatenatedString .= $parameters['ExternalReference'];
            $concatenatedString .= $sourceIp;

            // Generate SHA1 hash
            $sha1Hash = sha1($concatenatedString);

            // Sign the hash with private key
            $signature = '';
            $signResult = openssl_sign($sha1Hash, $signature, $privateKeyResource, OPENSSL_ALGO_SHA1);
            
            if (!$signResult) {
                throw new \Exception('Failed to create signature: ' . openssl_error_string());
            }

            // Base64 encode the signature
            $base64Signature = base64_encode($signature);

            // Free the private key resource
            openssl_free_key($privateKeyResource);

            return $base64Signature;

        } catch (\Exception $e) {
            Log::error('Yo Payments Deposit Signature Generation Error', [
                'error' => $e->getMessage(),
                'private_key_path' => $this->privateKeyPath,
            ]);
            throw $e;
        }
    }

    /**
     * Map Yo Payments status to our status
     */
    public function mapPaymentStatus($yoStatus)
    {
        $statusMap = [
            'SUCCEEDED' => 'completed',
            'SUCCESS' => 'completed',
            'SUCCESSFUL' => 'completed',
            'COMPLETED' => 'completed',
            'PENDING' => 'pending',
            'FAILED' => 'failed',
            'INDETERMINATE' => 'pending', // Pending resolution by Yo Payments team
            'CANCELLED' => 'failed',
            'TIMEOUT' => 'failed',
            'REJECTED' => 'failed',
            'DECLINED' => 'failed',
        ];

        return $statusMap[strtoupper($yoStatus)] ?? 'pending';
    }

    /**
     * Generate unique transaction ID
     */
    public static function generateTransactionId()
    {
        return 'YO_' . time() . '_' . rand(1000, 9999);
    }

    /**
     * Generate unique nonce for public key authentication
     */
    protected function generateNonce()
    {
        return 'nonce_' . time() . '_' . rand(1000, 9999);
    }

    /**
     * Generate RSA signature for public key authentication
     */
    protected function generateSignature($parameters, $nonce)
    {
        try {
            // Read private key
            $privateKey = file_get_contents($this->privateKeyPath);
            if (!$privateKey) {
                throw new \Exception('Private key file not found or unreadable');
            }

            // Create private key resource
            $privateKeyResource = openssl_pkey_get_private($privateKey);
            if (!$privateKeyResource) {
                throw new \Exception('Invalid private key format');
            }

            // Concatenate parameters as per Yo Payments specification
            $concatenatedString = $this->username;
            $concatenatedString .= $parameters['Amount'];
            $concatenatedString .= $parameters['Account'];
            $concatenatedString .= substr($parameters['Narrative'], 0, 255); // Limit to 255 characters
            $concatenatedString .= substr($parameters['ExternalReference'], 0, 255); // Limit to 255 characters
            $concatenatedString .= substr($nonce, 0, 255); // Limit to 255 characters

            // Generate SHA1 hash
            $sha1Hash = sha1($concatenatedString);

            // Sign the hash with private key
            $signature = '';
            $signResult = openssl_sign($sha1Hash, $signature, $privateKeyResource, OPENSSL_ALGO_SHA1);
            
            if (!$signResult) {
                throw new \Exception('Failed to create signature: ' . openssl_error_string());
            }

            // Base64 encode the signature
            $base64Signature = base64_encode($signature);

            // Free the private key resource
            openssl_free_key($privateKeyResource);

            return $base64Signature;

        } catch (\Exception $e) {
            Log::error('Yo Payments Signature Generation Error', [
                'error' => $e->getMessage(),
                'private_key_path' => $this->privateKeyPath,
            ]);
            throw $e;
        }
    }

    /**
     * Comprehensive transaction verification using multiple methods
     * This method tries different approaches to verify transaction status
     */
    public function comprehensiveTransactionVerification($transactionIdOrTransaction, $externalReference = null)
    {
        try {
            // Debug logging to see what we received
            Log::info('YoPaymentsService: comprehensiveTransactionVerification called', [
                'received_type' => gettype($transactionIdOrTransaction),
                'is_object' => is_object($transactionIdOrTransaction),
                'has_transaction_id_method' => is_object($transactionIdOrTransaction) ? method_exists($transactionIdOrTransaction, 'transaction_id') : 'N/A',
                'has_transaction_id_property' => is_object($transactionIdOrTransaction) ? property_exists($transactionIdOrTransaction, 'transaction_id') : 'N/A',
                'class_name' => is_object($transactionIdOrTransaction) ? get_class($transactionIdOrTransaction) : 'N/A',
                'received_value' => is_object($transactionIdOrTransaction) ? ($transactionIdOrTransaction->transaction_id ?? 'PROPERTY_NOT_ACCESSIBLE') : $transactionIdOrTransaction,
            ]);

            // If we received a transaction object, extract the Yo Payments reference
            if (is_object($transactionIdOrTransaction)) {
                $transaction = $transactionIdOrTransaction;
                
                // Try to get transaction_id from various possible sources
                $transactionId = null;
                if (method_exists($transaction, 'transaction_id')) {
                    $transactionId = $transaction->transaction_id;
                } elseif (property_exists($transaction, 'transaction_id')) {
                    $transactionId = $transaction->transaction_id;
                } elseif (isset($transaction->transaction_id)) {
                    $transactionId = $transaction->transaction_id;
                } elseif (is_string($transactionIdOrTransaction)) {
                    $transactionId = $transactionIdOrTransaction;
                }
                
                if ($transactionId && $transaction->payment_details) {
                    $yoPaymentsReference = $transaction->payment_details['yo_payments_reference'] ?? null;
                    $simulationModeValue = $transaction->payment_details['simulation_mode'] ?? false;
                    $isSimulated = $simulationModeValue === true || $simulationModeValue === 1 || $simulationModeValue === '1' || $simulationModeValue === 'true';
                    
                    Log::info('YoPaymentsService: Transaction object detected', [
                        'transaction_id' => $transactionId,
                        'yo_payments_reference' => $yoPaymentsReference,
                        'is_simulated' => $isSimulated,
                        'payment_details_keys' => $transaction->payment_details ? array_keys($transaction->payment_details) : 'null',
                        'simulation_mode_value' => $transaction->payment_details['simulation_mode'] ?? 'NOT_SET',
                        'simulation_mode_type' => isset($transaction->payment_details['simulation_mode']) ? gettype($transaction->payment_details['simulation_mode']) : 'NOT_SET',
                    ]);

                    // If this is a simulated transaction, return early with success
                    if ($isSimulated) {
                        Log::info('YoPaymentsService: Transaction is simulated, returning success', [
                            'transaction_id' => $transactionId,
                            'simulation_reference' => $yoPaymentsReference,
                        ]);

                        return [
                            'success' => true,
                            'status' => 'completed',
                            'message' => 'Payment verified successfully (Simulated Transaction)',
                            'verification_method' => 'simulation_mode',
                            'yo_payments_reference' => $yoPaymentsReference,
                            'simulation_mode' => true,
                            'verification_methods_tried' => ['simulation_detected'],
                            'comprehensive_verification' => true,
                        ];
                    } else {
                        Log::info('YoPaymentsService: Transaction is NOT simulated, proceeding with verification', [
                            'transaction_id' => $transactionId,
                            'simulation_mode_value' => $isSimulated,
                        ]);
                    }
                } else {
                    // Fallback: extract transaction_id from the transaction object
                    $transactionId = $transaction->transaction_id ?? 'unknown';
                    $yoPaymentsReference = $externalReference;
                    $isSimulated = false;
                    
                    Log::info('YoPaymentsService: Transaction object but no payment_details, using fallback', [
                        'transaction_id' => $transactionId,
                        'external_reference' => $externalReference,
                    ]);
                }
            } else {
                // Fallback: use the provided ID directly (should be a string)
                $transactionId = $transactionIdOrTransaction;
                $yoPaymentsReference = $externalReference;
                $isSimulated = false;
                
                Log::info('YoPaymentsService: Using fallback mode (no transaction object)', [
                    'transaction_id' => $transactionId,
                    'external_reference' => $externalReference,
                ]);
            }

            $verificationResults = [];
            
            // Method 1: Verify using Yo Payments reference (most reliable)
            if ($yoPaymentsReference && !$isSimulated) {
                try {
                    $result1 = $this->verifyPayment($transactionIdOrTransaction);
                    $verificationResults['method_1_yo_payments_reference'] = $result1;
                    
                    Log::info('YoPaymentsService: Method 1 (Yo Payments Reference) result', [
                        'transaction_id' => $transactionId,
                        'yo_payments_reference' => $yoPaymentsReference,
                        'success' => $result1['success'],
                        'status' => $result1['status'] ?? 'unknown',
                    ]);
                } catch (\Exception $e) {
                    Log::warning('YoPaymentsService: Method 1 failed', [
                        'transaction_id' => $transactionId,
                        'yo_payments_reference' => $yoPaymentsReference,
                        'error' => $e->getMessage(),
                    ]);
                    $verificationResults['method_1_yo_payments_reference'] = [
                        'success' => false,
                        'message' => 'Method 1 failed: ' . $e->getMessage(),
                    ];
                }
            } else {
                Log::info('YoPaymentsService: Skipping Method 1', [
                    'transaction_id' => $transactionId,
                    'yo_payments_reference' => $yoPaymentsReference,
                    'is_simulated' => $isSimulated,
                    'reason' => $isSimulated ? 'transaction_is_simulated' : 'no_reference',
                ]);
            }

            // Method 2: Verify using transaction ID as external reference (PULL type - preferred for transaction checking)
            try {
                $result2 = $this->checkTransactionByReference($transactionId, 'PULL', $transactionId);
                $verificationResults['method_2_transaction_id_as_reference'] = $result2;
                
                Log::info('YoPaymentsService: Method 2 (Transaction ID as Reference - PULL) result', [
                    'transaction_id' => $transactionId,
                    'success' => $result2['success'],
                    'status' => $result2['status'] ?? 'unknown',
                ]);
            } catch (\Exception $e) {
                Log::warning('YoPaymentsService: Method 2 failed', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]);
                $verificationResults['method_2_transaction_id_as_reference'] = [
                    'success' => false,
                    'message' => 'Method 2 failed: ' . $e->getMessage(),
                ];
            }

            // Method 3: Try with PUSH type as fallback (though PULL is preferred for transaction checking)
            try {
                $result3 = $this->checkTransactionByReference($transactionId, 'PUSH', $transactionId);
                $verificationResults['method_3_push_type'] = $result3;
                
                Log::info('YoPaymentsService: Method 3 (PUSH type - fallback) result', [
                    'transaction_id' => $transactionId,
                    'success' => $result3['success'],
                    'status' => $result3['status'] ?? 'unknown',
                ]);
            } catch (\Exception $e) {
                Log::warning('YoPaymentsService: Method 3 failed', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]);
                $verificationResults['method_3_push_type'] = [
                    'success' => false,
                    'message' => 'Method 3 failed: ' . $e->getMessage(),
                ];
            }

            // Analyze results and return the best available status
            $bestResult = $this->analyzeVerificationResults($verificationResults);
            
            Log::info('YoPaymentsService: Comprehensive verification completed', [
                'transaction_id' => $transactionId,
                'yo_payments_reference' => $yoPaymentsReference,
                'is_simulated' => $isSimulated,
                'best_result' => $bestResult,
                'all_results' => $verificationResults,
            ]);

            return $bestResult;

        } catch (\Exception $e) {
            Log::error('YoPaymentsService: Comprehensive verification failed', [
                'transaction_id' => $transactionId ?? 'unknown',
                'yo_payments_reference' => $yoPaymentsReference ?? 'unknown',
                'is_simulated' => $isSimulated ?? false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Comprehensive verification failed: ' . $e->getMessage(),
                'verification_methods_tried' => array_keys($verificationResults ?? []),
            ];
        }
    }

    /**
     * Analyze verification results and return the best available status
     */
    private function analyzeVerificationResults($verificationResults)
    {
        $successfulResults = [];
        $failedResults = [];

        foreach ($verificationResults as $method => $result) {
            if ($result['success']) {
                $successfulResults[$method] = $result;
            } else {
                $failedResults[$method] = $result;
            }
        }

        // If we have successful results, return the best one
        if (!empty($successfulResults)) {
            // Prioritize completed status
            foreach ($successfulResults as $method => $result) {
                if (($result['status'] ?? '') === 'completed') {
                    return array_merge($result, [
                        'verification_method' => $method,
                        'verification_methods_tried' => array_keys($verificationResults),
                        'comprehensive_verification' => true,
                    ]);
                }
            }

            // Return the first successful result
            $firstSuccessful = reset($successfulResults);
            return array_merge($firstSuccessful, [
                'verification_method' => array_key_first($successfulResults),
                'verification_methods_tried' => array_keys($verificationResults),
                'comprehensive_verification' => true,
            ]);
        }

        // If all methods failed, return a comprehensive error
        $errorMessages = [];
        foreach ($failedResults as $method => $result) {
            $errorMessages[] = "{$method}: {$result['message']}";
        }

        return [
            'success' => false,
            'message' => 'All verification methods failed. ' . implode('; ', $errorMessages),
            'verification_methods_tried' => array_keys($verificationResults),
            'comprehensive_verification' => true,
            'all_errors' => $failedResults,
        ];
    }

    /**
     * Check account balance for a phone number
     * 
     * @param string $phoneNumber
     * @param string $providerCode (MTN, AIRTEL, etc.) - Will be converted to MTN_UGANDA or AIRTEL_UGANDA
     * @return array
     */
    public function checkAccountBalance($phoneNumber, $providerCode = 'MTN')
    {
        try {
            $parameters = [
                'Account' => $this->formatPhoneNumberForBalance($phoneNumber),
                'AccountProviderCode' => $this->getProviderCode($providerCode),
            ];

            $xmlRequest = $this->buildXmlRequest('acgetbalance', $parameters);
            
            Log::info('Yo Payments Balance Check Request', [
                'phone_number' => $phoneNumber,
                'provider' => $providerCode,
                'method' => 'acgetbalance',
            ]);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments Balance Check Response', [
                'phone_number' => $phoneNumber,
                'response' => $response,
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'balance' => $response['data']['Balance'] ?? 0,
                    'currency' => $response['data']['Currency'] ?? 'UGX',
                    'account_status' => $response['data']['AccountStatus'] ?? 'unknown',
                    'message' => 'Balance retrieved successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to check balance: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Balance Check Error', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Balance check error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate account/phone number
     * 
     * @param string $phoneNumber
     * @param string $providerCode
     * @return array
     */
    public function validateAccount($phoneNumber, $providerCode = 'MTN')
    {
        try {
            $parameters = [
                'Account' => $this->formatPhoneNumberForValidation($phoneNumber),
                'AccountProviderCode' => $this->getProviderCode($providerCode),
            ];

            $xmlRequest = $this->buildXmlRequest('acvalidateaccount', $parameters);
            
            Log::info('Yo Payments Account Validation Request', [
                'phone_number' => $phoneNumber,
                'provider' => $providerCode,
                'method' => 'acvalidateaccount',
            ]);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments Account Validation Response', [
                'phone_number' => $phoneNumber,
                'response' => $response,
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'is_valid' => true,
                    'account_name' => $response['data']['AccountName'] ?? 'Unknown',
                    'account_status' => $response['data']['AccountStatus'] ?? 'unknown',
                    'message' => 'Account validated successfully',
                ];
            }

            return [
                'success' => false,
                'is_valid' => false,
                'message' => 'Account validation failed: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Account Validation Error', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'is_valid' => false,
                'message' => 'Account validation error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get transaction history for a phone number
     * 
     * @param string $phoneNumber
     * @param string $providerCode
     * @param int $limit
     * @return array
     */
    public function getTransactionHistory($phoneNumber, $providerCode = 'MTN', $limit = 10)
    {
        try {
            $parameters = [
                'Account' => $this->formatPhoneNumberForHistory($phoneNumber),
                'AccountProviderCode' => $this->getProviderCode($providerCode),
                'Limit' => $limit,
            ];

            $xmlRequest = $this->buildXmlRequest('acgettransactionhistory', $parameters);
            
            Log::info('Yo Payments Transaction History Request', [
                'phone_number' => $phoneNumber,
                'provider' => $providerCode,
                'limit' => $limit,
                'method' => 'acgettransactionhistory',
            ]);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments Transaction History Response', [
                'phone_number' => $phoneNumber,
                'response' => $response,
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'transactions' => $response['data']['Transactions'] ?? [],
                    'total_count' => $response['data']['TotalCount'] ?? 0,
                    'message' => 'Transaction history retrieved successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get transaction history: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Transaction History Error', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Transaction history error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel a pending transaction
     * 
     * @param string $transactionReference
     * @return array
     */
    public function cancelTransaction($transactionReference)
    {
        try {
            $parameters = [
                'TransactionReference' => $transactionReference,
            ];

            $xmlRequest = $this->buildXmlRequest('accanceltransaction', $parameters);
            
            Log::info('Yo Payments Cancel Transaction Request', [
                'transaction_reference' => $transactionReference,
                'method' => 'accanceltransaction',
            ]);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments Cancel Transaction Response', [
                'transaction_reference' => $transactionReference,
                'response' => $response,
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'cancelled' => true,
                    'message' => 'Transaction cancelled successfully',
                ];
            }

            return [
                'success' => false,
                'cancelled' => false,
                'message' => 'Failed to cancel transaction: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Cancel Transaction Error', [
                'transaction_reference' => $transactionReference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'cancelled' => false,
                'message' => 'Cancel transaction error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get API status and health check
     * 
     * @return array
     */
    public function getApiStatus()
    {
        try {
            $parameters = [];

            $xmlRequest = $this->buildXmlRequest('acgetstatus', $parameters);
            
            Log::info('Yo Payments API Status Check Request', [
                'method' => 'acgetstatus',
            ]);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments API Status Check Response', [
                'response' => $response,
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'api_status' => $response['data']['Status'] ?? 'unknown',
                    'server_time' => $response['data']['ServerTime'] ?? null,
                    'version' => $response['data']['Version'] ?? 'unknown',
                    'message' => 'API status retrieved successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get API status: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments API Status Check Error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'API status check error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the correct provider code format for Yo Payments API
     * 
     * @param string $providerCode
     * @return string
     */
    protected function getProviderCode($providerCode)
    {
        $providerCode = strtoupper(trim($providerCode));
        
        switch ($providerCode) {
            case 'MTN':
            case 'MTN_UGANDA':
                return 'MTN_UGANDA';
            case 'AIRTEL':
            case 'AIRTEL_UGANDA':
                return 'AIRTEL_UGANDA';
            default:
                // Return as-is for any other provider codes
                return $providerCode;
        }
    }

    /**
     * Build unified notification URL for all payment responses
     * 
     * @param array $additionalParams
     * @return string
     */
    protected function buildUnifiedNotificationUrl($additionalParams)
    {
        // Use unified IPN URL from config
        $baseUrl = config("services.yo_payments.ipn_urls.unified", route('payment.ipn'));
        
        // Add custom parameters if provided
        if (!empty($additionalParams['notification_params'])) {
            $queryParams = http_build_query($additionalParams['notification_params']);
            $baseUrl .= (strpos($baseUrl, '?') === false ? '?' : '&') . $queryParams;
        }
        
        // Properly encode the URL as per Yo Payments API specification
        $encodedUrl = str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $baseUrl
        );
        
        Log::info('YoPaymentsService: Unified notification URL built', [
            'original_url' => $baseUrl,
            'encoded_url' => $encodedUrl,
        ]);
        
        return $encodedUrl;
    }

    /**
     * Determine the type of notification based on request data
     * 
     * @param Request $request
     * @return string
     */
    protected function determineNotificationType($request)
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
} 