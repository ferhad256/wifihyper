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
        'duration_unit',
        'duration_value',
        'data_limit_mb',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Set sort_order attribute - ensure it's never null
     */
    public function setSortOrderAttribute($value)
    {
        $this->attributes['sort_order'] = $value ?? 0;
    }

    /**
     * Set duration_hours attribute - handle empty strings
     */
    public function setDurationHoursAttribute($value)
    {
        $this->attributes['duration_hours'] = $value && $value !== '' ? (int)$value : null;
    }

    /**
     * Set duration_value attribute - handle empty strings
     */
    public function setDurationValueAttribute($value)
    {
        $this->attributes['duration_value'] = $value && $value !== '' ? (float)$value : null;
    }

    /**
     * Set duration_unit attribute - ensure valid unit
     */
    public function setDurationUnitAttribute($value)
    {
        $validUnits = ['minutes', 'hours', 'days', 'weeks', 'months'];
        $this->attributes['duration_unit'] = in_array($value, $validUnits) ? $value : 'hours';
    }

    /**
     * Set data_limit_mb attribute - handle empty strings
     */
    public function setDataLimitMbAttribute($value)
    {
        $this->attributes['data_limit_mb'] = $value && $value !== '' ? (int)$value : null;
    }

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
        // Use new flexible duration system if available
        if ($this->duration_value && $this->duration_unit) {
            $unit = $this->duration_unit;
            $value = $this->duration_value;
            
            // Handle pluralization
            if ($value != 1) {
                $unit = rtrim($unit, 's') . 's';
            }
            
            return $value . ' ' . $unit;
        }
        
        // Fallback to old duration_hours system
        if (!$this->duration_hours) {
            return 'Unlimited';
        }
        
        if ($this->duration_hours < 24) {
            return $this->duration_hours . ' hours';
        }
        
        $days = $this->duration_hours / 24;
        return $days . ' days';
    }

    /**
     * Get duration in hours for compatibility
     */
    public function getDurationInHoursAttribute()
    {
        if ($this->duration_value && $this->duration_unit) {
            switch ($this->duration_unit) {
                case 'minutes':
                    return $this->duration_value / 60;
                case 'hours':
                    return $this->duration_value;
                case 'days':
                    return $this->duration_value * 24;
                case 'weeks':
                    return $this->duration_value * 24 * 7;
                case 'months':
                    return $this->duration_value * 24 * 30; // Approximate
                default:
                    return $this->duration_value;
            }
        }
        
        return $this->duration_hours;
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
