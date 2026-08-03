<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WithdrawalTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'withdrawal_id',
        'amount',
        'fee',
        'net_amount',
        'phone_number',
        'currency',
        'status',
        'description',
        'payment_details',
        'processed_at',
        'completed_at',
        'failed_at',
        'admin_id',
        'admin_notes',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'payment_details' => 'array',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the withdrawal transaction
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the admin who processed the withdrawal
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Check if withdrawal is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if withdrawal is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if withdrawal is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if withdrawal is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if withdrawal is approved
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Check if withdrawal is rejected
     */
    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    /**
     * Check if tenant has a pending withdrawal request
     * (includes both 'pending' and 'processing' status)
     */
    public static function hasPendingWithdrawal(int $tenantId): bool
    {
        return self::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'processing'])
            ->whereNull('approved_at')
            ->whereNull('rejected_at')
            ->exists();
    }

    /**
     * Get tenant's pending withdrawal
     * (includes both 'pending' and 'processing' status)
     */
    public static function getPendingWithdrawal(int $tenantId): ?self
    {
        return self::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'processing'])
            ->whereNull('approved_at')
            ->whereNull('rejected_at')
            ->first();
    }

    /**
     * Mark withdrawal as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark withdrawal as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark withdrawal as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);
    }

    /**
     * Generate unique withdrawal ID
     */
    public static function generateWithdrawalId(): string
    {
        return 'WDR_' . time() . '_' . rand(1000, 9999);
    }
}
