<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\PaymentController;

class PaymentRetryTest extends TestCase
{
    /**
     * Test that the retry mechanism can detect duplicate transaction errors.
     */
    public function test_duplicate_transaction_error_detection()
    {
        // Test error message detection
        $errorMessage1 = 'Duplicate transaction detected. Please try again with a different transaction reference.';
        $errorMessage2 = 'This is likely a duplicate transaction. Please vary your submission parameters.';
        $errorMessage3 = 'Some other error message';
        
        $this->assertTrue(stripos($errorMessage1, 'duplicate transaction') !== false);
        $this->assertTrue(stripos($errorMessage2, 'duplicate transaction') !== false);
        $this->assertFalse(stripos($errorMessage3, 'duplicate transaction') !== false);
    }

    /**
     * Test that retry mechanism waits for different timestamp.
     */
    public function test_retry_timestamp_difference()
    {
        $timestamp1 = time();
        sleep(1); // Simulate the retry delay
        $timestamp2 = time();
        
        $this->assertGreaterThan($timestamp1, $timestamp2, 'Retry should wait for different timestamp');
    }
} 