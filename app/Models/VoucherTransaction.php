<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'transaction_id',
        'sms_sent',
        'sms_sent_at',
        'sms_attempts',
        'last_sms_error',
        'voucher_displayed',
        'voucher_displayed_at',
    ];

    protected $casts = [
        'sms_sent' => 'boolean',
        'sms_sent_at' => 'datetime',
        'voucher_displayed' => 'boolean',
        'voucher_displayed_at' => 'datetime',
    ];

    /**
     * Get the voucher that owns the transaction
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Get the transaction that owns the voucher
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Mark SMS as sent
     */
    public function markSmsSent(): void
    {
        $this->update([
            'sms_sent' => true,
            'sms_sent_at' => now(),
        ]);
    }

    /**
     * Record SMS attempt
     */
    public function recordSmsAttempt(?string $error = null): void
    {
        $this->increment('sms_attempts');
        
        if ($error) {
            $this->update(['last_sms_error' => $error]);
        }
    }

    /**
     * Mark voucher as displayed
     */
    public function markVoucherDisplayed(): void
    {
        $this->update([
            'voucher_displayed' => true,
            'voucher_displayed_at' => now(),
        ]);
    }

    /**
     * Check if voucher SMS was already sent
     */
    public function isSmsSent(): bool
    {
        return $this->sms_sent;
    }

    /**
     * Check if voucher was already displayed
     */
    public function isVoucherDisplayed(): bool
    {
        return $this->voucher_displayed;
    }
}
