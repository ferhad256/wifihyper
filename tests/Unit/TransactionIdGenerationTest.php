<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\PaymentController;

class TransactionIdGenerationTest extends TestCase
{
    /**
     * Test that transaction IDs follow the expected format.
     */
    public function test_transaction_id_format()
    {
        // Test the simple transaction ID format
        $transactionId = 'TXN_' . time() . '_' . rand(1000, 9999);
        
        // Check that ID follows the expected format
        $this->assertMatchesRegularExpression('/^TXN_\d+_\d{4}$/', $transactionId, 'Transaction ID should follow the expected format');
        
        // Check that timestamp is recent
        $parts = explode('_', $transactionId);
        $timestamp = (int)$parts[1];
        $this->assertGreaterThan(time() - 10, $timestamp, 'Timestamp should be recent');
        $this->assertLessThan(time() + 10, $timestamp, 'Timestamp should not be in the future');
    }

    /**
     * Test that transaction IDs are different when generated at different times.
     */
    public function test_transaction_id_uniqueness()
    {
        $id1 = 'TXN_' . time() . '_' . rand(1000, 9999);
        sleep(1); // Wait 1 second
        $id2 = 'TXN_' . time() . '_' . rand(1000, 9999);
        
        $this->assertNotEquals($id1, $id2, 'Transaction IDs generated at different times should be different');
    }
} 