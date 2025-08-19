<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added this import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('is_active');
        });
        
        // Update existing tenants to have verified emails (for existing data)
        // This runs after the column is created
        DB::statement("UPDATE tenants SET email_verified_at = created_at, is_active = 1 WHERE email_verified_at IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
