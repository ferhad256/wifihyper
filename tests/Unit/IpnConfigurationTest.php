<?php

namespace Tests\Unit;

use Tests\TestCase;

class IpnConfigurationTest extends TestCase
{
    /**
     * Test that IPN configuration is properly loaded.
     */
    public function test_ipn_configuration_is_loaded()
    {
        $this->assertIsArray(config('services.yo_payments.ipn_urls'));
        $this->assertEquals('https://wifihyper.com/payment/ipn', config('services.yo_payments.ipn_urls.unified'));
        $this->assertTrue(config('services.yo_payments.ipn_enabled'));
        $this->assertEquals(30, config('services.yo_payments.ipn_timeout'));
        $this->assertEquals(3, config('services.yo_payments.ipn_retry_attempts'));
    }

    /**
     * Test that IPN environment variables are set.
     */
    public function test_ipn_environment_variables()
    {
        $this->assertEquals('https://wifihyper.com/payment/ipn', env('YO_PAYMENTS_IPN_UNIFIED_URL'));
        $this->assertEquals('true', env('YO_PAYMENTS_IPN_ENABLED'));
        $this->assertEquals('30', env('YO_PAYMENTS_IPN_TIMEOUT'));
        $this->assertEquals('3', env('YO_PAYMENTS_IPN_RETRY_ATTEMPTS'));
    }

    /**
     * Test that SMS configuration is loaded.
     */
    public function test_sms_configuration_is_loaded()
    {
        $this->assertIsArray(config('services.ug_sms'));
        $this->assertEquals('WIFIHYPER', config('services.ug_sms.sender_id'));
    }
} 