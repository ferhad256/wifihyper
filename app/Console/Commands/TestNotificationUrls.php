<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoPaymentsService;

class TestNotificationUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notification-urls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test notification URL generation and encoding for Yo Payments IPN';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Notification URL Generation...');
        
        // Create YoPaymentsService instance
        $yoService = new YoPaymentsService();
        
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($yoService);
        $method = $reflection->getMethod('buildNotificationUrl');
        $method->setAccessible(true);
        
        $this->info('Testing Success Notification URL:');
        $successUrl = $method->invoke($yoService, 'success', []);
        $this->line("Success URL: {$successUrl}");
        
        $this->info('Testing Failure Notification URL:');
        $failureUrl = $method->invoke($yoService, 'failure', []);
        $this->line("Failure URL: {$failureUrl}");
        
        $this->info('Testing with custom parameters:');
        $customParams = [
            'notification_params' => [
                'source' => 'yo_payments',
                'type' => 'success',
                'transaction_id' => 'TXN_123456'
            ]
        ];
        
        $successUrlWithParams = $method->invoke($yoService, 'success', $customParams);
        $this->line("Success URL with params: {$successUrlWithParams}");
        
        $this->info('Testing XML escaping:');
        $this->line("Original: https://wifihyper.com/payment/callback?key1=value&key2=test");
        $this->line("Escaped: " . str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            'https://wifihyper.com/payment/callback?key1=value&key2=test'
        ));
        
        $this->info('Configuration check:');
        $this->line("IPN Success URL: " . config('services.yo_payments.ipn_urls.success'));
        $this->line("IPN Failure URL: " . config('services.yo_payments.ipn_urls.failure'));
        $this->line("IPN Enabled: " . (config('services.yo_payments.ipn_enabled') ? 'Yes' : 'No'));
        
        $this->info('Notification URL test completed!');
        
        // Clean up
        unlink(__FILE__);
    }
} 