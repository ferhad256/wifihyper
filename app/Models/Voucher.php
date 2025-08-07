<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hotspot_id',
        'package_id',
        'code',
        'status',
        'phone_number',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Set expires_at attribute - handle empty strings
     */
    public function setExpiresAtAttribute($value)
    {
        $this->attributes['expires_at'] = $value && $value !== '' ? $value : null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotspot(): BelongsTo
    {
        return $this->belongsTo(Hotspot::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function smsLog(): BelongsTo
    {
        return $this->belongsTo(SmsLog::class);
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->status === 'used';
    }

    public function isAvailable(): bool
    {
        return $this->status === 'unused' && !$this->isExpired();
    }

    public function markAsUsed(string $phoneNumber = null): void
    {
        $this->update([
            'status' => 'used',
            'used_at' => now(),
            'phone_number' => $phoneNumber,
        ]);
    }
}
