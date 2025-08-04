<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotspot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'ssid',
        'location',
        'description',
        'is_active',
        'captive_portal_url',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Accessors
    public function getActivePackagesAttribute()
    {
        return $this->packages()->where('is_active', true)->get();
    }
}
