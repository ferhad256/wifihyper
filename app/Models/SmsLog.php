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
        'service' => 'string',
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
}