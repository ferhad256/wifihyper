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

    /**
     * Get URL-friendly name for the hotspot
     */
    public function getUrlNameAttribute()
    {
        // Convert to lowercase and replace spaces with hyphens
        $name = strtolower($this->name);
        // Remove special characters except alphanumeric, hyphens, and underscores
        $name = preg_replace('/[^a-z0-9\-_]/', '', $name);
        // Remove multiple consecutive hyphens
        $name = preg_replace('/-+/', '-', $name);
        // Remove leading and trailing hyphens
        $name = trim($name, '-');
        
        return $name ?: 'hotspot-' . $this->id;
    }
}
