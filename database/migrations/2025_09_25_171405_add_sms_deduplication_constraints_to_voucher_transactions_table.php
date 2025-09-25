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
        Schema::table('voucher_transactions', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate SMS per transaction
            $table->unique('transaction_id', 'unique_transaction_sms');
            
            // Add index for faster lookups
            $table->index(['sms_sent', 'created_at'], 'idx_sms_sent_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_transactions', function (Blueprint $table) {
            $table->dropUnique('unique_transaction_sms');
            $table->dropIndex('idx_sms_sent_created');
        });
    }
};