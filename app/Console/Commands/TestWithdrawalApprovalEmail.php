<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use App\Services\EmailService;
use Illuminate\Console\Command;

class TestWithdrawalApprovalEmail extends Command
{
    protected $signature = 'test:withdrawal-approval-email {tenant_email}';
    protected $description = 'Test withdrawal approval email functionality';

    public function handle()
    {
        $email = $this->argument('tenant_email');
        
        $this->info("Testing withdrawal approval email for: {$email}");
        
        $tenant = Tenant::where('email', $email)->first();
        
        if (!$tenant) {
            $this->error("Tenant not found with email: {$email}");
            return;
        }
        
        $this->info("Found tenant: {$tenant->name}");
        
        // Create a mock withdrawal transaction
        $withdrawal = new WithdrawalTransaction([
            'id' => 999,
            'amount' => 50000,
            'status' => 'completed',
            'approved_at' => now(),
            'admin_notes' => 'Test withdrawal approval',
        ]);
        
        // Mock admin relationship
        $withdrawal->setRelation('admin', (object)['name' => 'Test Admin']);
        
        $this->info("Mock withdrawal: UGX " . number_format($withdrawal->amount));
        $this->info("Current balance: UGX " . number_format($tenant->wallet_balance));
        
        // Test email service
        $emailService = new EmailService();
        
        try {
            $result = $emailService->sendWithdrawalApprovalEmail($tenant, $withdrawal);
            
            if ($result) {
                $this->info("✅ Withdrawal approval email sent successfully!");
            } else {
                $this->error("❌ Failed to send withdrawal approval email");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}
