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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('business_name')->nullable();
            $table->text('address')->nullable();
            $table->decimal('wallet_balance', 10, 2)->default(0.00);
            $table->string('subscription_plan')->default('basic');
            $table->date('subscription_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('payment_gateway')->nullable();
            $table->json('payment_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
