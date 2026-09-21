<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants authenticate through a real guard from here on, and Laravel's
 * SessionGuard writes a remember token whenever "remember me" is used. The
 * column never existed because tenant auth was a hand-rolled session key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
