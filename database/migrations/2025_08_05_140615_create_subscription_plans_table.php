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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Starter, Professional, Enterprise
            $table->string('slug')->unique(); // starter, professional, enterprise
            $table->text('description');
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            
            // Plan Limits
            $table->integer('max_hotspots')->default(3);
            $table->integer('max_vouchers_per_month')->default(5000);
            $table->integer('max_users')->default(1);
            $table->integer('max_transactions_per_month')->default(1000);
            
            // Transaction Fees (JSON)
            $table->json('transaction_fees')->nullable();
            
            // Features (JSON)
            $table->json('features')->nullable();
            $table->json('restrictions')->nullable();
            
            // Portal Settings
            $table->boolean('custom_portal')->default(false);
            $table->boolean('source_code_access')->default(false);
            $table->boolean('api_access')->default(false);
            $table->boolean('priority_support')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
