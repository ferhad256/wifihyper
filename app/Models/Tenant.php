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
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'payment_settings' => 'array',
        'settings' => 'array',
    ];

    /**
     * Check if tenant can create more hotspots
     * Removed subscription limits - all tenants can create unlimited hotspots
     */
    public function canCreateHotspot()
    {
        return true;
    }

    /**
     * Check if tenant can upload more vouchers this month
     * Removed subscription limits - all tenants can upload unlimited vouchers
     */
    public function canUploadVouchers()
    {
        return true;
    }

    /**
     * Check if tenant can create more transactions
     * Removed subscription limits - all tenants can create unlimited transactions
     */
    public function canCreateTransaction($currentMonthTransactions)
    {
        return true;
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
     * Using updated fee structure for voucher purchases
     */
    public function getTransactionFee($amount)
    {
        // Updated fee structure for all tenants
        if ($amount >= 1 && $amount <= 5000) {
            return $amount * 0.15; // 15% for UGX 1-5000
        } elseif ($amount >= 5001 && $amount <= 6999) {
            return $amount * 0.13; // 13% for UGX 5001-6999
        } elseif ($amount >= 7000 && $amount <= 7999) {
            return $amount * 0.115; // 11.5% for UGX 7000-7999
        } elseif ($amount >= 8000 && $amount <= 8999) {
            return $amount * 0.11; // 11% for UGX 8000-8999
        } elseif ($amount >= 9000 && $amount <= 9999) {
            return $amount * 0.10; // 10% for UGX 9000-9999
        } elseif ($amount >= 10000 && $amount <= 10999) {
            return $amount * 0.095; // 9.5% for UGX 10000-10999
        } elseif ($amount >= 11000 && $amount <= 19999) {
            return $amount * 0.09; // 9% for UGX 11000-19999
        } elseif ($amount >= 20000) {
            return $amount * 0.05; // 5% for UGX 20000 and above
        } else {
            return $amount * 0.15; // Default 15% for amounts below 1 UGX
        }
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

    public function withdrawalTransactions(): HasMany
    {
        return $this->hasMany(WithdrawalTransaction::class);
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
