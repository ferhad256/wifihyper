<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class TestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test {tenant_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the notification system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
        } else {
            $tenant = Tenant::first();
        }

        if (!$tenant) {
            $this->error('No tenant found.');
            return Command::FAILURE;
        }

        $this->info("Testing notifications for tenant: {$tenant->name}");

        $notificationService = new NotificationService();

        // Test low voucher notifications
        $this->info('Checking low voucher notifications...');
        $result = $notificationService->checkLowVoucherNotifications($tenant);
        $this->info($result ? 'Low voucher check completed.' : 'Low voucher check failed.');

        // Test no voucher notifications
        $this->info('Checking no voucher notifications...');
        $notificationService->checkNoVoucherNotifications($tenant);
        $this->info('No voucher check completed.');

        // Show current notifications
        $notifications = $tenant->notifications()->latest()->get();
        $this->info("Total notifications: {$notifications->count()}");
        
        foreach ($notifications as $notification) {
            $this->line("- [{$notification->status}] {$notification->title}: {$notification->message}");
        }

        return Command::SUCCESS;
    }
} 