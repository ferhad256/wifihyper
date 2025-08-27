<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test configuration loading for debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Configuration Loading...');
        $this->newLine();

        // Test environment variables
        $this->info('Environment Variables:');
        $this->line('YO_PAYMENTS_USERNAME: ' . (env('YO_PAYMENTS_USERNAME') ?: 'NOT SET'));
        $this->line('YO_PAYMENTS_PASSWORD: ' . (env('YO_PAYMENTS_PASSWORD') ?: 'NOT SET'));
        $this->line('YO_PAYMENTS_BASE_URL: ' . (env('YO_PAYMENTS_BASE_URL') ?: 'NOT SET'));
        $this->newLine();

        // Test config values
        $this->info('Config Values:');
        $this->line('services.yo_payments.username: ' . (config('services.yo_payments.username') ?: 'NOT SET'));
        $this->line('services.yo_payments.password: ' . (config('services.yo_payments.password') ?: 'NOT SET'));
        $this->line('services.yo_payments.base_url: ' . (config('services.yo_payments.base_url') ?: 'NOT SET'));
        $this->newLine();

        // Test full services config
        $this->info('Full Services Config:');
        $services = config('services');
        foreach (array_keys($services) as $service) {
            $this->line("- $service");
        }
        $this->newLine();

        // Test specific service
        if (isset($services['yo_payments'])) {
            $this->info('Yo Payments Config:');
            foreach ($services['yo_payments'] as $key => $value) {
                $this->line("  $key: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value));
            }
        } else {
            $this->error('yo_payments configuration is missing!');
        }

        $this->newLine();
        $this->info('Configuration test completed.');
    }
} 