<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoPaymentsService;

class TestProviderCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:provider-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test provider code conversion for Yo Payments API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Provider Code Conversion...');
        
        // Create a mock YoPaymentsService instance to test the protected method
        $yoService = new YoPaymentsService();
        
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($yoService);
        $method = $reflection->getMethod('getProviderCode');
        $method->setAccessible(true);
        
        $testCases = [
            'MTN' => 'MTN_UGANDA',
            'mtn' => 'MTN_UGANDA',
            'MTN_UGANDA' => 'MTN_UGANDA',
            'AIRTEL' => 'AIRTEL_UGANDA',
            'airtel' => 'AIRTEL_UGANDA',
            'AIRTEL_UGANDA' => 'AIRTEL_UGANDA',
            'UNKNOWN' => 'UNKNOWN',
        ];
        
        $this->table(
            ['Input', 'Expected Output', 'Actual Output', 'Status'],
            collect($testCases)->map(function ($expected, $input) use ($method, $yoService) {
                $actual = $method->invoke($yoService, $input);
                $status = $actual === $expected ? '✅ PASS' : '❌ FAIL';
                
                return [
                    $input,
                    $expected,
                    $actual,
                    $status
                ];
            })->toArray()
        );
        
        $this->info('Provider code conversion test completed!');
        
        // Clean up
        unlink(__FILE__);
    }
} 