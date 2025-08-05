<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;

class PlanLimitService
{
    /**
     * Check if tenant can create a new hotspot
     */
    public function canCreateHotspot(Tenant $tenant): array
    {
        $plan = $tenant->getCurrentPlan();
        $currentCount = $tenant->hotspots()->count();
        $canCreate = $plan->canCreateHotspot($currentCount);
        
        return [
            'can_create' => $canCreate,
            'current_count' => $currentCount,
            'max_allowed' => $plan->max_hotspots,
            'remaining' => $plan->max_hotspots === -1 ? -1 : max(0, $plan->max_hotspots - $currentCount),
            'plan_name' => $plan->name,
        ];
    }

    /**
     * Check if tenant can upload more vouchers this month
     */
    public function canUploadVouchers(Tenant $tenant): array
    {
        $plan = $tenant->getCurrentPlan();
        $currentMonthVouchers = $tenant->vouchers()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $canUpload = $plan->canUploadVouchers($currentMonthVouchers);
        
        return [
            'can_upload' => $canUpload,
            'current_month_count' => $currentMonthVouchers,
            'max_allowed' => $plan->max_vouchers_per_month,
            'remaining' => $plan->max_vouchers_per_month === -1 ? -1 : max(0, $plan->max_vouchers_per_month - $currentMonthVouchers),
            'plan_name' => $plan->name,
        ];
    }

    /**
     * Check if tenant can create more transactions this month
     */
    public function canCreateTransaction(Tenant $tenant): array
    {
        $plan = $tenant->getCurrentPlan();
        $currentMonthTransactions = $tenant->transactions()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $canCreate = $plan->canCreateTransaction($currentMonthTransactions);
        
        return [
            'can_create' => $canCreate,
            'current_month_count' => $currentMonthTransactions,
            'max_allowed' => $plan->max_transactions_per_month,
            'remaining' => $plan->max_transactions_per_month === -1 ? -1 : max(0, $plan->max_transactions_per_month - $currentMonthTransactions),
            'plan_name' => $plan->name,
        ];
    }

    /**
     * Get transaction fee for a specific amount
     */
    public function getTransactionFee(Tenant $tenant, float $amount): array
    {
        $plan = $tenant->getCurrentPlan();
        $fee = $plan->getTransactionFee($amount);
        $totalAmount = $amount + $fee;
        
        return [
            'original_amount' => $amount,
            'fee_amount' => $fee,
            'total_amount' => $totalAmount,
            'fee_percentage' => $this->getFeePercentage($plan, $amount),
            'plan_name' => $plan->name,
        ];
    }

    /**
     * Get fee percentage for a specific amount
     */
    private function getFeePercentage(SubscriptionPlan $plan, float $amount): float
    {
        if (!$plan->transaction_fees) {
            return 0;
        }

        foreach ($plan->transaction_fees as $fee) {
            if ($amount >= $fee['min'] && $amount <= $fee['max']) {
                return $fee['percentage'];
            }
        }

        return 0;
    }

    /**
     * Check if tenant has access to a specific feature
     */
    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        $plan = $tenant->getCurrentPlan();
        return $plan->hasFeature($feature);
    }

    /**
     * Check if tenant has a specific restriction
     */
    public function hasRestriction(Tenant $tenant, string $restriction): bool
    {
        $plan = $tenant->getCurrentPlan();
        return $plan->hasRestriction($restriction);
    }

    /**
     * Get plan usage summary for tenant
     */
    public function getPlanUsage(Tenant $tenant): array
    {
        $plan = $tenant->getCurrentPlan();
        
        // Get current month stats
        $currentMonthVouchers = $tenant->vouchers()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
            
        $currentMonthTransactions = $tenant->transactions()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
            
        $currentHotspots = $tenant->hotspots()->count();
        
        return [
            'plan' => [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => $plan->getFormattedPrice(),
                'description' => $plan->description,
            ],
            'usage' => [
                'hotspots' => [
                    'current' => $currentHotspots,
                    'max' => $plan->max_hotspots,
                    'remaining' => $plan->max_hotspots === -1 ? -1 : max(0, $plan->max_hotspots - $currentHotspots),
                    'can_create' => $plan->canCreateHotspot($currentHotspots),
                ],
                'vouchers' => [
                    'current_month' => $currentMonthVouchers,
                    'max_per_month' => $plan->max_vouchers_per_month,
                    'remaining' => $plan->max_vouchers_per_month === -1 ? -1 : max(0, $plan->max_vouchers_per_month - $currentMonthVouchers),
                    'can_upload' => $plan->canUploadVouchers($currentMonthVouchers),
                ],
                'transactions' => [
                    'current_month' => $currentMonthTransactions,
                    'max_per_month' => $plan->max_transactions_per_month,
                    'remaining' => $plan->max_transactions_per_month === -1 ? -1 : max(0, $plan->max_transactions_per_month - $currentMonthTransactions),
                    'can_create' => $plan->canCreateTransaction($currentMonthTransactions),
                ],
            ],
            'features' => $plan->features,
            'restrictions' => $plan->restrictions,
            'transaction_fees' => $plan->transaction_fees,
        ];
    }

    /**
     * Log plan limit violation
     */
    public function logLimitViolation(Tenant $tenant, string $action, array $details = []): void
    {
        Log::warning('Plan limit violation', [
            'tenant_id' => $tenant->id,
            'tenant_email' => $tenant->email,
            'action' => $action,
            'plan' => $tenant->getCurrentPlan()->name,
            'details' => $details,
        ]);
    }

    /**
     * Get available plans for upgrade
     */
    public function getAvailablePlans(): array
    {
        return SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'monthly_price' => $plan->monthly_price,
                    'yearly_price' => $plan->yearly_price,
                    'formatted_monthly_price' => $plan->getFormattedPrice('monthly'),
                    'formatted_yearly_price' => $plan->getFormattedPrice('yearly'),
                    'is_featured' => $plan->is_featured,
                    'features' => $plan->features,
                    'max_hotspots' => $plan->max_hotspots,
                    'max_vouchers_per_month' => $plan->max_vouchers_per_month,
                    'transaction_fees' => $plan->transaction_fees,
                ];
            })
            ->toArray();
    }
} 