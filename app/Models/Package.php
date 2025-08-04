<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotspot_id',
        'name',
        'description',
        'price',
        'duration_hours',
        'data_limit_mb',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function hotspot(): BelongsTo
    {
        return $this->belongsTo(Hotspot::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Accessors
    public function getFormattedPriceAttribute()
    {
        return 'UGX ' . number_format($this->price, 0);
    }

    public function getFormattedDurationAttribute()
    {
        if (!$this->duration_hours) {
            return 'Unlimited';
        }
        
        if ($this->duration_hours < 24) {
            return $this->duration_hours . ' hours';
        }
        
        $days = $this->duration_hours / 24;
        return $days . ' days';
    }

    public function getFormattedDataLimitAttribute()
    {
        if (!$this->data_limit_mb) {
            return 'Unlimited';
        }
        
        if ($this->data_limit_mb < 1024) {
            return $this->data_limit_mb . ' MB';
        }
        
        $gb = $this->data_limit_mb / 1024;
        return number_format($gb, 1) . ' GB';
    }
}
