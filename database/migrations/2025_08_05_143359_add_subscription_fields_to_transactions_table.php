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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type')->default('voucher')->after('status'); // voucher, subscription
            $table->json('data')->nullable()->after('type'); // Additional data for subscription transactions
            $table->timestamp('completed_at')->nullable()->after('data'); // When subscription was completed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['type', 'data', 'completed_at']);
        });
    }
};
