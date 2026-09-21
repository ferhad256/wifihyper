<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Tenant extends Authenticatable
{
    use HasFactory, Notifiable;

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
        'remember_token',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'payment_settings' => 'array',
        'settings' => 'array',
        // Hashes on assignment, and is idempotent - an already-hashed value
        // passes straight through, so no caller can double-hash a password.
        'password' => 'hashed',
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
     * Fee structure: 2.8% of amount
     */
    public function getTransactionFee($amount)
    {
        return ($amount * \App\Services\TransactionFeeService::FEE_PERCENTAGE) / 100;
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

    /**
     * This tenant's in-app alerts (low voucher stock, payments, and so on).
     *
     * Named alerts() rather than notifications() because the Notifiable trait
     * defines a notifications() morphMany of its own, and a same-named
     * relation of a different shape would silently shadow it.
     *
     * Laravel's own database notifications cannot be used here regardless: the
     * App\Models\Notification table is literally named `notifications`, which
     * is the name DatabaseNotification hardcodes.
     */
    public function alerts(): HasMany
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
            ->sum('amount') - 
            $this->withdrawalTransactions()
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
            ->sum('amount') - 
            $this->withdrawalTransactions()
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
