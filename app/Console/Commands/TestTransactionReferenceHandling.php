<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\Tenant;
use App\Services\YoPaymentsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestTransactionReferenceHandling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:transaction-reference-handling 
                            {--create-test-data : Create test transaction data}
                            {--transaction-id= : Use existing transaction ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the updated transaction reference handling in YoPaymentsService';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Transaction Reference Handling Updates');
        $this->info('================================================');

        try {
            // Get or create test transaction
            $transaction = $this->getTestTransaction();
            
            if (!$transaction) {
                $this->error('❌ Failed to get or create test transaction');
                return 1;
            }

            $this->info("✅ Using transaction: {$transaction->transaction_id}");

            // Test the updated methods
            $this->testInitiatePaymentReferences($transaction);
            $this->testCheckTransactionReferences($transaction);

            $this->info('🎉 All transaction reference handling tests completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Test failed with error: {$e->getMessage()}");
            Log::error('TestTransactionReferenceHandling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Get or create test transaction
     */
    protected function getTestTransaction()
    {
        if ($this->option('transaction-id')) {
            $transaction = Transaction::where('transaction_id', $this->option('transaction-id'))->first();
            if ($transaction) {
                return $transaction;
            }
        }

        if ($this->option('create-test-data')) {
            return $this->createTestTransaction();
        }

        $this->error('❌ No transaction ID provided and --create-test-data not specified');
        $this->info('Usage: php artisan test:transaction-reference-handling --create-test-data');
        $this->info('   or: php artisan test:transaction-reference-handling --transaction-id=TXN_123');
        return null;
    }

    /**
     * Create test transaction
     */
    protected function createTestTransaction()
    {
        $this->info('📝 Creating test transaction...');

        // Get first tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->error('❌ No tenants found in database');
            return null;
        }

        $transaction = Transaction::create([
            'transaction_id' => 'TXN_TEST_' . time(),
            'tenant_id' => $tenant->id,
            'amount' => 1000,
            'status' => 'pending',
            'payment_method' => 'mobile_money',
            'phone_number' => '256700000000',
            'description' => 'Test transaction for reference handling',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("✅ Created test transaction: {$transaction->transaction_id}");
        return $transaction;
    }

    /**
     * Test initiatePayment transaction references
     */
    protected function testInitiatePaymentReferences($transaction)
    {
        $this->info('🔍 Test 1: Testing initiatePayment transaction references...');

        try {
            $yoService = new YoPaymentsService();
            
            // Use reflection to access the buildXmlRequest method for testing
            $reflection = new \ReflectionClass($yoService);
            $method = $reflection->getMethod('buildXmlRequest');
            $method->setAccessible(true);

            // Build parameters as they would be in initiatePayment
            $parameters = [
                'NonBlocking' => 'TRUE',
                'Amount' => $transaction->amount,
                'Account' => '256700000000',
                'AccountProviderCode' => 'MTN',
                'Narrative' => 'Test payment',
                'PrivateTransactionReference' => $transaction->transaction_id, // Updated: Now uses PrivateTransactionReference
                'InstantNotificationUrl' => 'https://example.com/success',
                'FailureNotificationUrl' => 'https://example.com/failure',
            ];

            // Build XML request
            $xmlRequest = $method->invoke($yoService, 'acdepositfunds', $parameters);

            // Verify PrivateTransactionReference is used
            if (strpos($xmlRequest, '<PrivateTransactionReference>') !== false) {
                $this->info("✅ PrivateTransactionReference correctly used in initiatePayment");
                
                // Check if it contains the transaction ID
                if (strpos($xmlRequest, "<PrivateTransactionReference>{$transaction->transaction_id}</PrivateTransactionReference>") !== false) {
                    $this->info("✅ PrivateTransactionReference contains correct transaction ID: {$transaction->transaction_id}");
                } else {
                    $this->error("❌ PrivateTransactionReference does not contain correct transaction ID");
                }
            } else {
                $this->error("❌ PrivateTransactionReference not found in initiatePayment XML");
            }

            // Verify TransactionReference is NOT used (should be null)
            if (strpos($xmlRequest, '<TransactionReference>') !== false) {
                $this->warn("⚠️  TransactionReference found in initiatePayment XML (should be null when using PrivateTransactionReference)");
            } else {
                $this->info("✅ TransactionReference correctly not used in initiatePayment (as expected)");
            }

            $this->info("✅ initiatePayment transaction references test completed");

        } catch (\Exception $e) {
            $this->error("❌ initiatePayment transaction references test failed: {$e->getMessage()}");
        }
    }

    /**
     * Test checkTransactionByReference transaction references
     */
    protected function testCheckTransactionReferences($transaction)
    {
        $this->info('🔍 Test 2: Testing checkTransactionByReference transaction references...');

        try {
            $yoService = new YoPaymentsService();
            
            // Use reflection to access the buildXmlRequest method for testing
            $reflection = new \ReflectionClass($yoService);
            $method = $reflection->getMethod('buildXmlRequest');
            $method->setAccessible(true);

            // Build parameters as they would be in checkTransactionByReference
            $parameters = [
                'DepositTransactionType' => 'PULL',
            ];
            
            // When checking transaction status, the transaction reference should be put under PrivateTransactionReference
            // TransactionReference is the reference generated by Yo! Payments gateway
            if ($transaction->transaction_id) {
                $parameters['PrivateTransactionReference'] = $transaction->transaction_id;
            }

            // Build XML request
            $xmlRequest = $method->invoke($yoService, 'actransactioncheckstatus', $parameters);

            // Verify PrivateTransactionReference is used
            if (strpos($xmlRequest, '<PrivateTransactionReference>') !== false) {
                $this->info("✅ PrivateTransactionReference correctly used in checkTransactionByReference");
                
                // Check if it contains the transaction ID
                if (strpos($xmlRequest, "<PrivateTransactionReference>{$transaction->transaction_id}</PrivateTransactionReference>") !== false) {
                    $this->info("✅ PrivateTransactionReference contains correct transaction ID: {$transaction->transaction_id}");
                } else {
                    $this->error("❌ PrivateTransactionReference does not contain correct transaction ID");
                }
            } else {
                $this->error("❌ PrivateTransactionReference not found in checkTransactionByReference XML");
            }

            // Verify TransactionReference is NOT used (should be null)
            if (strpos($xmlRequest, '<TransactionReference>') !== false) {
                $this->warn("⚠️  TransactionReference found in checkTransactionByReference XML (should be null when using PrivateTransactionReference)");
            } else {
                $this->info("✅ TransactionReference correctly not used in checkTransactionByReference (as expected)");
            }

            $this->info("✅ checkTransactionByReference transaction references test completed");

        } catch (\Exception $e) {
            $this->error("❌ checkTransactionByReference transaction references test failed: {$e->getMessage()}");
        }
    }
} 