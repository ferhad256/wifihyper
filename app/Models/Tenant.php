<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_changed_at',
        'phone',
        'business_name',
        'address',
        'wallet_balance',
        'subscription_plan_id',
        'subscription_expires_at',
        'is_active',
        'email_verified_at',
        'payment_gateway',
        'payment_settings',
        'settings',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
        'subscription_expires_at' => 'date',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'payment_settings' => 'array',
        'settings' => 'array',
    ];

    /**
     * Get the subscription plan for this tenant
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Get current subscription plan
     */
    public function getCurrentPlan()
    {
        // If tenant has a subscription plan, return it
        if ($this->subscriptionPlan) {
            return $this->subscriptionPlan;
        }

        // If no subscription plan, get the default plan
        $defaultPlan = SubscriptionPlan::getDefaultPlan();
        
        if (!$defaultPlan) {
            // If no default plan exists, create a fallback
            \Log::warning('No default subscription plan found, creating fallback', [
                'tenant_id' => $this->id,
                'tenant_email' => $this->email
            ]);
            
            // Create a basic fallback plan
            $defaultPlan = SubscriptionPlan::create([
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Basic plan for existing users',
                'monthly_price' => 0.00,
                'yearly_price' => 0.00,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'max_hotspots' => 3,
                'max_vouchers_per_month' => 5000,
                'max_users' => 1,
                'max_transactions_per_month' => 1000,
                'transaction_fees' => [
                    [
                        'min' => 0,
                        'max' => 1000,
                        'percentage' => 15,
                        'description' => 'UGX 1000 and below - 15%'
                    ],
                    [
                        'min' => 1001,
                        'max' => 5000,
                        'percentage' => 10,
                        'description' => 'UGX 1000 to 5000 - 10%'
                    ],
                    [
                        'min' => 5001,
                        'max' => 999999999,
                        'percentage' => 5,
                        'description' => 'UGX 5000 and above - 5%'
                    ]
                ],
                'features' => [
                    'basic_wifi_management',
                    'voucher_system',
                    'payment_processing',
                    'sms_notifications',
                    'email_notifications',
                    'basic_analytics',
                    'default_captive_portal',
                    'mobile_responsive',
                    'multi_tenant_support'
                ],
                'restrictions' => [
                    'no_source_code_access',
                    'no_custom_portal',
                    'no_api_access',
                    'no_priority_support',
                    'limited_analytics',
                    'standard_support'
                ]
            ]);
        }

        return $defaultPlan;
    }

    /**
     * Check if tenant can create more hotspots
     */
    public function canCreateHotspot()
    {
        $plan = $this->getCurrentPlan();
        $currentCount = $this->hotspots()->count();
        return $plan->canCreateHotspot($currentCount);
    }

    /**
     * Check if tenant can upload more vouchers this month
     */
    public function canUploadVouchers()
    {
        $plan = $this->getCurrentPlan();
        $currentMonthVouchers = $this->vouchers()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        return $plan->canUploadVouchers($currentMonthVouchers);
    }

    /**
     * Check if tenant can create more transactions
     */
    public function canCreateTransaction($currentMonthTransactions)
    {
        return $currentMonthTransactions < $this->max_transactions_per_month;
    }

    /**
     * Check if email is verified
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => now(),
            'is_active' => true
        ])->save();
    }

    /**
     * Check if tenant can access the system (email verified and active)
     */
    public function canAccessSystem(): bool
    {
        return $this->hasVerifiedEmail() && $this->is_active;
    }

    /**
     * Get transaction fee for a specific amount
     */
    public function getTransactionFee($amount)
    {
        $plan = $this->getCurrentPlan();
        return $plan->getTransactionFee($amount);
    }

    public function hotspots(): HasMany
    {
        return $this->hasMany(Hotspot::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // Accessors
    public function getTotalSalesAttribute()
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getNetSalesAttribute()
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->sum('net_amount');
    }

    public function getTotalFeesAttribute()
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->sum('transaction_fee');
    }

    public function getTodaySalesAttribute()
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('amount');
    }

    public function getTodayNetSalesAttribute()
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('net_amount');
    }

    public function getUnusedVouchersCountAttribute()
    {
        return $this->vouchers()
            ->where('status', 'unused')
            ->count();
    }
}
