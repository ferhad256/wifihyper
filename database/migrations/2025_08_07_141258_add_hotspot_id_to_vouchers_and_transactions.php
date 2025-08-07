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
        // Add hotspot_id to vouchers table
        if (!Schema::hasColumn('vouchers', 'hotspot_id')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->foreignId('hotspot_id')->nullable()->after('tenant_id')->constrained()->onDelete('cascade');
            });
        }
        
        // Add hotspot_id to transactions table
        if (!Schema::hasColumn('transactions', 'hotspot_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('hotspot_id')->nullable()->after('tenant_id')->constrained()->onDelete('cascade');
            });
        }
        
        // Update existing data
        $this->updateExistingData();
        
        // Add indexes
        Schema::table('vouchers', function (Blueprint $table) {
            $table->index(['tenant_id', 'hotspot_id']);
            $table->index(['package_id', 'status']);
        });
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
            $table->index(['hotspot_id', 'status']);
        });
        
        Schema::table('packages', function (Blueprint $table) {
            $table->index(['hotspot_id', 'is_active']);
        });
        
        Schema::table('hotspots', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'hotspot_id']);
            $table->dropIndex(['package_id', 'status']);
        });
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['hotspot_id', 'status']);
        });
        
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['hotspot_id', 'is_active']);
        });
        
        Schema::table('hotspots', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_active']);
        });
        
        // Remove columns
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['hotspot_id']);
            $table->dropColumn('hotspot_id');
        });
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['hotspot_id']);
            $table->dropColumn('hotspot_id');
        });
    }
    
    /**
     * Update existing data with hotspot_id
     */
    private function updateExistingData()
    {
        // Update vouchers
        $vouchers = \App\Models\Voucher::whereNull('hotspot_id')->get();
        foreach ($vouchers as $voucher) {
            $package = \App\Models\Package::find($voucher->package_id);
            if ($package) {
                $voucher->update(['hotspot_id' => $package->hotspot_id]);
            } else {
                $hotspot = \App\Models\Hotspot::where('tenant_id', $voucher->tenant_id)->first();
                if ($hotspot) {
                    $voucher->update(['hotspot_id' => $hotspot->id]);
                }
            }
        }
        
        // Update transactions
        $transactions = \App\Models\Transaction::whereNull('hotspot_id')->get();
        foreach ($transactions as $transaction) {
            $package = \App\Models\Package::find($transaction->package_id);
            if ($package) {
                $transaction->update(['hotspot_id' => $package->hotspot_id]);
            } else {
                $hotspot = \App\Models\Hotspot::where('tenant_id', $transaction->tenant_id)->first();
                if ($hotspot) {
                    $transaction->update(['hotspot_id' => $hotspot->id]);
                }
            }
        }
    }
};
