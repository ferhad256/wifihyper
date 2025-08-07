<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hotspot_id',
        'package_id',
        'voucher_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'phone_number',
        'transaction_fee',
        'net_amount',
        'gateway_response',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'fee_percentage' => 'decimal:2',
        'payment_details' => 'array',
        'paid_at' => 'datetime',
        'data' => 'array',
        'completed_at' => 'datetime',
    ];

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

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return $this->currency . ' ' . number_format($this->amount, 0);
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'completed' => '<span class="badge bg-success">Completed</span>',
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'failed' => '<span class="badge bg-danger">Failed</span>',
            'refunded' => '<span class="badge bg-info">Refunded</span>',
            default => '<span class="badge bg-secondary">Unknown</span>',
        };
    }
}
