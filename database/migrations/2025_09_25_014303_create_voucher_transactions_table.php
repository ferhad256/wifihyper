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
        Schema::create('voucher_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('sms_sent_at')->nullable();
            $table->integer('sms_attempts')->default(0);
            $table->text('last_sms_error')->nullable();
            $table->boolean('voucher_displayed')->default(false);
            $table->timestamp('voucher_displayed_at')->nullable();
            $table->timestamps();
            
            // Ensure one voucher transaction per transaction
            $table->unique('transaction_id');
            
            // Indexes for performance
            $table->index(['voucher_id', 'sms_sent']);
            $table->index(['transaction_id', 'sms_sent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_transactions');
    }
};