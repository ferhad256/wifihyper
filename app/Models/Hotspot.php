<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotspot extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($hotspot) {
            if (empty($hotspot->url_name)) {
                $urlName = static::generateUrlName($hotspot->name, $hotspot->id);
                $hotspot->url_name = $urlName;
                $hotspot->save();
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'ssid',
        'location',
        'description',
        'is_active',
        'captive_portal_url',
        'settings',
        'url_name',
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

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
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
     * Generate URL-friendly name for the hotspot
     */
    public static function generateUrlName($name, $id)
    {
        // Convert to lowercase and replace spaces with hyphens
        $urlName = strtolower($name);
        // Remove special characters except alphanumeric, hyphens, and underscores
        $urlName = preg_replace('/[^a-z0-9\-_]/', '', $urlName);
        // Remove multiple consecutive hyphens
        $urlName = preg_replace('/-+/', '-', $urlName);
        // Remove leading and trailing hyphens
        $urlName = trim($urlName, '-');
        
        $baseUrlName = $urlName ?: 'hotspot-' . $id;
        
        // Ensure uniqueness
        $counter = 1;
        $finalUrlName = $baseUrlName;
        while (static::where('url_name', $finalUrlName)->exists()) {
            $finalUrlName = $baseUrlName . '-' . $counter;
            $counter++;
        }
        
        return $finalUrlName;
    }

    /**
     * Find hotspot by URL name
     */
    public static function findByUrlName($urlName)
    {
        // First try to find by url_name field
        $hotspot = static::where('url_name', $urlName)->first();
        
        if ($hotspot) {
            return $hotspot;
        }
        
        // Fallback: for hotspots that might not have url_name set yet
        // Generate URL names for all hotspots without them and check
        $hotspotsWithoutUrlName = static::whereNull('url_name')->get();
        foreach ($hotspotsWithoutUrlName as $hotspot) {
            $generatedUrlName = static::generateUrlName($hotspot->name, $hotspot->id);
            $hotspot->url_name = $generatedUrlName;
            $hotspot->save();
            
            if ($generatedUrlName === $urlName) {
                return $hotspot;
            }
        }
        
        return null;
    }
}
