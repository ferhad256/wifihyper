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
            $table->decimal('transaction_fee', 10, 2)->default(0)->after('amount');
            $table->decimal('net_amount', 10, 2)->default(0)->after('transaction_fee');
            $table->decimal('fee_percentage', 5, 2)->default(0)->after('net_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['transaction_fee', 'net_amount', 'fee_percentage']);
        });
    }
};
