<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hotspots', function (Blueprint $table) {
            $table->string('url_name')->unique()->nullable()->after('name');
            $table->index('url_name');
        });

        // Populate url_name for existing hotspots
        $hotspots = \App\Models\Hotspot::all();
        foreach ($hotspots as $hotspot) {
            $urlName = $this->generateUrlName($hotspot->name, $hotspot->id);
            $hotspot->update(['url_name' => $urlName]);
        }

        // Keep url_name nullable for flexibility
        // It will be populated automatically when needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotspots', function (Blueprint $table) {
            $table->dropIndex(['url_name']);
            $table->dropColumn('url_name');
        });
    }

    /**
     * Generate URL-friendly name for hotspot
     */
    private function generateUrlName($name, $id)
    {
        // Convert to lowercase and replace spaces with hyphens
        $urlName = strtolower($name);
        // Remove special characters except alphanumeric, hyphens, and underscores
        $urlName = preg_replace('/[^a-z0-9\-_]/', '', $urlName);
        // Remove multiple consecutive hyphens
        $urlName = preg_replace('/-+/', '-', $urlName);
        // Remove leading and trailing hyphens
        $urlName = trim($urlName, '-');
        
        return $urlName ?: 'hotspot-' . $id;
    }
};
