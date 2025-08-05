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
        'phone',
        'business_name',
        'address',
        'wallet_balance',
        'subscription_plan_id',
        'subscription_expires_at',
        'is_active',
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
        return $this->subscriptionPlan ?? SubscriptionPlan::getDefaultPlan();
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
     * Check if tenant can create more transactions this month
     */
    public function canCreateTransaction()
    {
        $plan = $this->getCurrentPlan();
        $currentMonthTransactions = $this->transactions()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        return $plan->canCreateTransaction($currentMonthTransactions);
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
