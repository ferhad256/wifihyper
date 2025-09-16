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
        Schema::create('withdrawal_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('withdrawal_id')->unique(); // Unique withdrawal identifier
            $table->decimal('amount', 10, 2); // Amount to withdraw
            $table->decimal('fee', 10, 2)->default(0); // Withdrawal fee
            $table->decimal('net_amount', 10, 2); // Amount after fees
            $table->string('phone_number'); // Phone number to withdraw to
            $table->string('currency', 3)->default('UGX');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('description')->nullable();
            $table->json('payment_details')->nullable(); // JPesa response data
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['withdrawal_id']);
            $table->index(['phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_transactions');
    }
};
