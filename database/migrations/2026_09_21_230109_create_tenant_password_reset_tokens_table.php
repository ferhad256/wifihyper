<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A password-reset broker table of the tenants' own.
 *
 * The shared `password_reset_tokens` table has `email` as its primary key and
 * is already claimed by the `users` broker, so a tenant and a user with the
 * same address cannot both hold a live token. Giving tenants their own table
 * removes that collision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_password_reset_tokens');
    }
};
