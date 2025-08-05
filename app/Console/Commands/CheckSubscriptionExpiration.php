<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:check-expiration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired subscriptions and downgrade to Starter plan';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired subscriptions...');

        // Get the Starter plan
        $starterPlan = SubscriptionPlan::where('slug', 'starter')->first();
        
        if (!$starterPlan) {
            $this->error('Starter plan not found!');
            return 1;
        }

        // Find tenants with expired subscriptions
        $expiredTenants = Tenant::where('subscription_expires_at', '<', now())
            ->where('subscription_plan_id', '!=', $starterPlan->id)
            ->get();

        $this->info("Found {$expiredTenants->count()} tenants with expired subscriptions.");

        $downgradedCount = 0;

        foreach ($expiredTenants as $tenant) {
            $oldPlan = $tenant->subscriptionPlan;
            
            // Downgrade to Starter plan
            $tenant->update([
                'subscription_plan_id' => $starterPlan->id,
                'subscription_expires_at' => null, // Remove expiration for free plan
            ]);

            // Create notification for the tenant
            $tenant->notifications()->create([
                'title' => 'Subscription Expired',
                'message' => "Your {$oldPlan->name} subscription has expired. You have been automatically downgraded to the Starter plan.",
                'type' => 'subscription_expired',
                'data' => [
                    'old_plan_name' => $oldPlan->name,
                    'new_plan_name' => $starterPlan->name,
                    'expired_at' => now()->toISOString(),
                ],
                'is_read' => false,
            ]);

            $this->info("Downgraded tenant {$tenant->name} ({$tenant->email}) from {$oldPlan->name} to {$starterPlan->name}");
            
            Log::info('Tenant subscription downgraded due to expiration', [
                'tenant_id' => $tenant->id,
                'tenant_email' => $tenant->email,
                'old_plan' => $oldPlan->name,
                'new_plan' => $starterPlan->name,
                'expired_at' => now()->toISOString(),
            ]);

            $downgradedCount++;
        }

        $this->info("Successfully downgraded {$downgradedCount} tenants to Starter plan.");

        return 0;
    }
}
