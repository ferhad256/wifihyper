<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'voucher_id',
        'phone_number',
        'message',
        'status',
        'message_id',
        'gateway_response',
        'sent_at',
        'service',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'sent_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    // Accessors
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'sent' => '<span class="badge bg-success">Sent</span>',
            'delivered' => '<span class="badge bg-success">Delivered</span>',
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'failed' => '<span class="badge bg-danger">Failed</span>',
            default => '<span class="badge bg-secondary">Unknown</span>',
        };
    }
}
