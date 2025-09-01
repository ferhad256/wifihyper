<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\YoPaymentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class YoPaymentsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $yoPaymentsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yoPaymentsService = new YoPaymentsService();
    }

    /**
     * Test that YoPaymentsService can be instantiated.
     */
    public function test_service_can_be_instantiated()
    {
        $this->assertInstanceOf(YoPaymentsService::class, $this->yoPaymentsService);
    }

    /**
     * Test provider code formatting.
     */
    public function test_provider_code_formatting()
    {
        $reflection = new \ReflectionClass($this->yoPaymentsService);
        $method = $reflection->getMethod('getProviderCode');
        $method->setAccessible(true);

        $this->assertEquals('MTN_UGANDA', $method->invoke($this->yoPaymentsService, 'MTN'));
        $this->assertEquals('AIRTEL_UGANDA', $method->invoke($this->yoPaymentsService, 'AIRTEL'));
        $this->assertEquals('CUSTOM_PROVIDER', $method->invoke($this->yoPaymentsService, 'CUSTOM_PROVIDER'));
    }

    /**
     * Test phone number formatting.
     */
    public function test_phone_number_formatting()
    {
        $reflection = new \ReflectionClass($this->yoPaymentsService);
        $method = $reflection->getMethod('formatPhoneNumberForDeposit');
        $method->setAccessible(true);

        $this->assertEquals('256704791624', $method->invoke($this->yoPaymentsService, '0704791624'));
        $this->assertEquals('256704791624', $method->invoke($this->yoPaymentsService, '+256704791624'));
        $this->assertEquals('256704791624', $method->invoke($this->yoPaymentsService, '256704791624'));
    }
} 