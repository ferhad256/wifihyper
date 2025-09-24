<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\SmsLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyVoucherSystem extends Command
{
    protected $signature = 'verify:voucher-system {--detailed : Show detailed analysis}';
    protected $description = 'Verify voucher system integrity and prevent duplicate sending';

    public function handle()
    {
        $this->info('🔍 Verifying Voucher System Integrity...');
        $this->newLine();

        // Check for duplicate voucher usage
        $this->checkDuplicateVoucherUsage();
        
        // Check for transactions without vouchers
        $this->checkTransactionsWithoutVouchers();
        
        // Check for vouchers sent multiple times
        $this->checkMultipleVoucherSending();
        
        // Check voucher status consistency
        $this->checkVoucherStatusConsistency();
        
        // Check SMS logs integrity
        $this->checkSmsLogsIntegrity();
        
        // Check for race conditions
        $this->checkRaceConditions();

        $this->newLine();
        $this->info('✅ Voucher system verification completed!');
    }

    private function checkDuplicateVoucherUsage()
    {
        $this->info('1. Checking for duplicate voucher usage...');
        
        // Find vouchers used in multiple transactions
        $duplicates = DB::select("
            SELECT v.code, v.status, COUNT(t.id) as transaction_count
            FROM vouchers v
            JOIN transactions t ON v.id = t.voucher_id
            WHERE v.status = 'used'
            GROUP BY v.id, v.code, v.status
            HAVING COUNT(t.id) > 1
        ");

        if (count($duplicates) > 0) {
            $this->error("❌ Found " . count($duplicates) . " vouchers used in multiple transactions:");
            foreach ($duplicates as $duplicate) {
                $this->line("   - Voucher: {$duplicate->code} (used in {$duplicate->transaction_count} transactions)");
            }
        } else {
            $this->info("✅ No duplicate voucher usage found");
        }
    }

    private function checkTransactionsWithoutVouchers()
    {
        $this->info('2. Checking transactions without vouchers...');
        
        $transactionsWithoutVouchers = Transaction::whereNull('voucher_id')
            ->where('status', 'completed')
            ->count();

        if ($transactionsWithoutVouchers > 0) {
            $this->warn("⚠️  Found {$transactionsWithoutVouchers} completed transactions without vouchers");
        } else {
            $this->info("✅ All completed transactions have vouchers assigned");
        }
    }

    private function checkMultipleVoucherSending()
    {
        $this->info('3. Checking for multiple SMS sends for same voucher...');
        
        // Find vouchers that have multiple SMS logs
        $multipleSms = DB::select("
            SELECT v.code, COUNT(sl.id) as sms_count
            FROM vouchers v
            JOIN sms_logs sl ON v.code = sl.message
            WHERE v.status = 'used'
            GROUP BY v.id, v.code
            HAVING COUNT(sl.id) > 1
        ");

        if (count($multipleSms) > 0) {
            $this->error("❌ Found " . count($multipleSms) . " vouchers with multiple SMS sends:");
            foreach ($multipleSms as $sms) {
                $this->line("   - Voucher: {$sms->code} (sent {$sms->sms_count} times)");
            }
        } else {
            $this->info("✅ No multiple SMS sends found");
        }
    }

    private function checkVoucherStatusConsistency()
    {
        $this->info('4. Checking voucher status consistency...');
        
        // Find vouchers marked as used but have no used_at timestamp
        $inconsistentStatus = Voucher::where('status', 'used')
            ->whereNull('used_at')
            ->count();

        if ($inconsistentStatus > 0) {
            $this->error("❌ Found {$inconsistentStatus} vouchers marked as used but missing used_at timestamp");
        } else {
            $this->info("✅ All used vouchers have proper timestamps");
        }

        // Find vouchers with used_at but status is not used
        $inconsistentTimestamp = Voucher::whereNotNull('used_at')
            ->where('status', '!=', 'used')
            ->count();

        if ($inconsistentTimestamp > 0) {
            $this->error("❌ Found {$inconsistentTimestamp} vouchers with used_at timestamp but status is not 'used'");
        } else {
            $this->info("✅ All vouchers with timestamps have correct status");
        }
    }

    private function checkSmsLogsIntegrity()
    {
        $this->info('5. Checking SMS logs integrity...');
        
        // Find SMS logs without corresponding vouchers
        $orphanedSms = SmsLog::whereNotNull('voucher_id')
            ->whereDoesntHave('voucher')
            ->count();

        if ($orphanedSms > 0) {
            $this->warn("⚠️  Found {$orphanedSms} SMS logs with invalid voucher references");
        } else {
            $this->info("✅ All SMS logs have valid voucher references");
        }
    }

    private function checkRaceConditions()
    {
        $this->info('6. Checking for potential race conditions...');
        
        // Find transactions created within same second (potential race condition)
        $raceConditions = DB::select("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM transactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at), HOUR(created_at), MINUTE(created_at), SECOND(created_at)
            HAVING COUNT(*) > 5
            ORDER BY count DESC
            LIMIT 5
        ");

        if (count($raceConditions) > 0) {
            $this->warn("⚠️  Potential race conditions detected:");
            foreach ($raceConditions as $race) {
                $this->line("   - {$race->date}: {$race->count} transactions in same second");
            }
        } else {
            $this->info("✅ No obvious race conditions detected");
        }
    }
}
