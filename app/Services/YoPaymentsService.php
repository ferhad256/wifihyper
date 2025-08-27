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
        $this->baseUrl = config('services.yo_payments.base_url', 'https://paymentsapi1.yo.co.ug/ybs/task.php');
        $this->fallbackUrl = config('services.yo_payments.fallback_url', 'https://paymentsapi2.yo.co.ug/ybs/task.php');
        $this->username = config('services.yo_payments.username');
        $this->password = config('services.yo_payments.password');
        $this->privateKeyPath = config('services.yo_payments.private_key_path');
        $this->publicKeyEnabled = config('services.yo_payments.public_key_enabled', false);
    }

    /**
     * Initialize a payment transaction
     */
    public function initiatePayment(Transaction $transaction, $phoneNumber)
    {
        Log::info('YoPaymentsService: Payment initiation started', [
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
            Log::info('YoPaymentsService: Building payment parameters', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'phone_number' => $phoneNumber
            ]);

            $parameters = [
                'NonBlocking' => 'TRUE', // Use non-blocking for better performance
                'Amount' => $transaction->amount,
                'Account' => $this->formatPhoneNumberForDeposit($phoneNumber),
                'AccountProviderCode' => 'MTN', // Default to MTN, can be made configurable
                'Narrative' => "WiFi Package - " . ($transaction->package->name ?? 'Unknown Package'),
                'ExternalReference' => $transaction->transaction_id,
                'InstantNotificationUrl' => route('payment.callback'),
                'FailureNotificationUrl' => route('payment.failed.post'),
            ];

            Log::info('YoPaymentsService: Payment parameters built', [
                'transaction_id' => $transaction->transaction_id,
                'parameters' => $parameters,
                'callback_url' => route('payment.callback'),
                'failure_url' => route('payment.failed')
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
            'payment_details' => $simulatedResponse['data'],
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
     * Verify payment status
     */
    public function verifyPayment($transactionId)
    {
        try {
            $parameters = [
                'TransactionReference' => $transactionId,
                'DepositTransactionType' => 'PULL', // Default to pull deposit (acdepositfunds)
            ];

            $xmlRequest = $this->buildXmlRequest('actransactioncheckstatus', $parameters);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments Transaction Status Check', [
                'transaction_id' => $transactionId,
                'response' => $response,
            ]);

            if ($response['success']) {
                $data = $response['data'];
                $transactionStatus = $response['transaction_status'] ?? 'unknown';
                
                // Extract additional transaction details if available
                $transactionDetails = [];
                if (isset($data['Amount'])) {
                    $transactionDetails['amount'] = $data['Amount'];
                }
                if (isset($data['AmountFormatted'])) {
                    $transactionDetails['amount_formatted'] = $data['AmountFormatted'];
                }
                if (isset($data['CurrencyCode'])) {
                    $transactionDetails['currency_code'] = $data['CurrencyCode'];
                }
                if (isset($data['TransactionInitiationDate'])) {
                    $transactionDetails['initiation_date'] = $data['TransactionInitiationDate'];
                }
                if (isset($data['TransactionCompletionDate'])) {
                    $transactionDetails['completion_date'] = $data['TransactionCompletionDate'];
                }
                if (isset($data['IssuedReceiptNumber'])) {
                    $transactionDetails['receipt_number'] = $data['IssuedReceiptNumber'];
                }
                
                return [
                    'success' => true,
                    'status' => $this->mapPaymentStatus($transactionStatus),
                    'is_pending' => $response['is_pending'] ?? false,
                    'data' => $data,
                    'transaction_details' => $transactionDetails,
                    'message' => $response['message'] ?? 'Payment status retrieved successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to verify payment: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Transaction Status Check Error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment verification error: ' . $e->getMessage(),
            ];
        }
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
                $transactionId = $formData['external_ref'] ?? $formData['TransactionReference'] ?? '';
                $status = $formData['Status'] ?? 'OK'; // Default to OK if no status field
                $amount = $formData['amount'] ?? $formData['Amount'] ?? '';
                $currency = $formData['Currency'] ?? 'UGX';
                
                // If we have external_ref, this is a successful payment
                if ($formData['external_ref'] && !$status) {
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
                'AccountProviderCode' => 'MTN', // Default to MTN, can be made configurable
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
     * Build XML request
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

            // Concatenate parameters as per Yo Payments specification for deposits
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
     * Check transaction status using private transaction reference
     */
    public function checkTransactionByReference($externalReference, $depositType = 'PULL')
    {
        try {
            $parameters = [
                'PrivateTransactionReference' => $externalReference,
                'DepositTransactionType' => $depositType,
            ];

            $xmlRequest = $this->buildXmlRequest('actransactioncheckstatus', $parameters);

            $response = $this->makeXmlRequest($xmlRequest);

            Log::info('Yo Payments Transaction Status Check by Reference', [
                'external_reference' => $externalReference,
                'deposit_type' => $depositType,
                'response' => $response,
            ]);

            if ($response['success']) {
                $data = $response['data'];
                $transactionStatus = $response['transaction_status'] ?? 'unknown';
                
                // Extract additional transaction details if available
                $transactionDetails = [];
                if (isset($data['Amount'])) {
                    $transactionDetails['amount'] = $data['Amount'];
                }
                if (isset($data['AmountFormatted'])) {
                    $transactionDetails['amount_formatted'] = $data['AmountFormatted'];
                }
                if (isset($data['CurrencyCode'])) {
                    $transactionDetails['currency_code'] = $data['CurrencyCode'];
                }
                if (isset($data['TransactionInitiationDate'])) {
                    $transactionDetails['initiation_date'] = $data['TransactionInitiationDate'];
                }
                if (isset($data['TransactionCompletionDate'])) {
                    $transactionDetails['completion_date'] = $data['TransactionCompletionDate'];
                }
                if (isset($data['IssuedReceiptNumber'])) {
                    $transactionDetails['receipt_number'] = $data['IssuedReceiptNumber'];
                }
                
                return [
                    'success' => true,
                    'status' => $this->mapPaymentStatus($transactionStatus),
                    'is_pending' => $response['is_pending'] ?? false,
                    'data' => $data,
                    'transaction_details' => $transactionDetails,
                    'message' => $response['message'] ?? 'Transaction status retrieved successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to check transaction status: ' . $response['message'],
            ];

        } catch (\Exception $e) {
            Log::error('Yo Payments Transaction Status Check by Reference Error', [
                'external_reference' => $externalReference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Transaction status check error: ' . $e->getMessage(),
            ];
        }
    }
} 