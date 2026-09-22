<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admins get their own broker table for the same reason tenants do: the
 * shared password_reset_tokens table keys on email as its primary key.
 *
 * Nothing uses this yet - admins have no password-reset flow. It exists so the
 * admin panel can name its own broker explicitly rather than falling through
 * to the default one, which resolves against the Tenant model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
    }
};
