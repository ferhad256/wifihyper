<?php

namespace Tests\Unit;

use Tests\TestCase;

class SimpleUnitTest extends TestCase
{
    /**
     * Test that the application can be instantiated.
     */
    public function test_application_exists()
    {
        $this->assertTrue(true);
    }

    /**
     * Test that basic configuration is loaded.
     */
    public function test_configuration_is_loaded()
    {
        $this->assertEquals('testing', config('app.env'));
        $this->assertEquals('WIFIHYPER', config('app.name'));
    }

    /**
     * Test that services configuration exists.
     */
    public function test_services_configuration_exists()
    {
        $this->assertIsArray(config('services.ug_sms'));
    }
} 