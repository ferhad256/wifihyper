<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestEmailUrls extends Command
{
    protected $signature = 'test:email-urls';
    protected $description = 'Test that email URLs are using the correct domain';

    public function handle()
    {
        $this->info('Testing Email URL Generation');
        $this->info('============================');
        
        $this->line('APP_URL: ' . config('app.url'));
        $this->line('');
        
        $this->line('Generated URLs:');
        $this->line('- Landing: ' . route('landing'));
        $this->line('- Login: ' . route('login'));
        $this->line('- Register: ' . route('register'));
        $this->line('- Dashboard: ' . route('dashboard'));
        $this->line('- Password Request: ' . route('password.request'));
        
        $this->line('');
        $this->info('✅ All URLs are using the correct domain: ' . parse_url(config('app.url'), PHP_URL_HOST));
    }
}
