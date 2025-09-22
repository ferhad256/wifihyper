<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\EmailService;
use Illuminate\Console\Command;

class TestPasswordReset extends Command
{
    protected $signature = 'test:password-reset {email}';
    protected $description = 'Test password reset functionality';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing password reset for: {$email}");
        
        $tenant = Tenant::where('email', $email)->first();
        
        if (!$tenant) {
            $this->error("Tenant not found with email: {$email}");
            return;
        }
        
        $this->info("Found tenant: {$tenant->name}");
        
        // Test email service
        $emailService = new EmailService();
        $testUrl = route('password.reset', ['token' => 'test-token', 'email' => $email]);
        
        $this->info("Test reset URL: {$testUrl}");
        
        try {
            $result = $emailService->sendPasswordResetEmail($tenant, $testUrl);
            
            if ($result) {
                $this->info("✅ Password reset email sent successfully!");
            } else {
                $this->error("❌ Failed to send password reset email");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}
