<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'is_active',
        'is_featured',
        'sort_order',
        'max_hotspots',
        'max_vouchers_per_month',
        'max_users',
        'max_transactions_per_month',
        'transaction_fees',
        'features',
        'restrictions',
        'custom_portal',
        'source_code_access',
        'api_access',
        'priority_support',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'max_hotspots' => 'integer',
        'max_vouchers_per_month' => 'integer',
        'max_users' => 'integer',
        'max_transactions_per_month' => 'integer',
        'transaction_fees' => 'array',
        'features' => 'array',
        'restrictions' => 'array',
        'custom_portal' => 'boolean',
        'source_code_access' => 'boolean',
        'api_access' => 'boolean',
        'priority_support' => 'boolean',
    ];

    /**
     * Get transaction fees with proper JSON decoding
     */
    public function getTransactionFeesAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }
        return $value ?: [];
    }

    /**
     * Get features with proper JSON decoding
     */
    public function getFeaturesAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }
        return $value ?: [];
    }

    /**
     * Get restrictions with proper JSON decoding
     */
    public function getRestrictionsAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }
        return $value ?: [];
    }

    /**
     * Get tenants using this plan
     */
    public function tenants()
    {
        return $this->hasMany(Tenant::class, 'subscription_plan_id');
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured plans
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get transaction fee for a specific amount
     */
    public function getTransactionFee($amount)
    {
        if (!$this->transaction_fees) {
            return 0;
        }

        foreach ($this->transaction_fees as $fee) {
            if ($amount >= $fee['min'] && $amount <= $fee['max']) {
                return ($amount * $fee['percentage']) / 100;
            }
        }

        return 0;
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature($feature)
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Check if plan has a specific restriction
     */
    public function hasRestriction($restriction)
    {
        return in_array($restriction, $this->restrictions ?? []);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice($period = 'monthly')
    {
        $price = $period === 'yearly' ? $this->yearly_price : $this->monthly_price;
        
        if ($price == 0) {
            if ($this->slug === 'enterprise') {
                return 'Contact Sales';
            }
            return 'Free';
        }
        
        return 'UGX ' . number_format($price, 0);
    }

    /**
     * Get plan by slug
     */
    public static function findBySlug($slug)
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Get default plan (Starter)
     */
    public static function getDefaultPlan()
    {
        return static::where('slug', 'starter')->first();
    }

    /**
     * Check if tenant can create more hotspots
     */
    public function canCreateHotspot($currentHotspotCount)
    {
        return $currentHotspotCount < $this->max_hotspots;
    }

    /**
     * Check if tenant can upload more vouchers
     */
    public function canUploadVouchers($currentMonthVouchers)
    {
        return $currentMonthVouchers < $this->max_vouchers_per_month;
    }

    /**
     * Check if tenant can create more transactions
     */
    public function canCreateTransaction($currentMonthTransactions)
    {
        return $currentMonthTransactions < $this->max_transactions_per_month;
    }
}
