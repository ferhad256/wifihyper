<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class CleanupPendingPayments extends Command
{
    protected $signature = 'payments:cleanup-pending {--older-than=24 : Clean up transactions older than X hours} {--dry-run : Show what would be cleaned up without actually doing it}';
    protected $description = 'Clean up old pending transactions that may have failed or been abandoned';

    public function handle()
    {
        $olderThan = $this->option('older-than');
        $dryRun = $this->option('dry-run');

        $this->info("🧹 Starting cleanup of pending transactions older than {$olderThan} hours");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN (no changes)' : 'LIVE (will make changes)'));

        // Find old pending transactions
        $oldPendingTransactions = Transaction::where('status', 'pending')
            ->where('created_at', '<', now()->subHours($olderThan))
            ->get();

        if ($oldPendingTransactions->isEmpty()) {
            $this->info('✅ No old pending transactions found to clean up');
            return 0;
        }

        $this->info("📋 Found {$oldPendingTransactions->count()} old pending transactions");

        // Group by age for better reporting
        $groupedTransactions = $oldPendingTransactions->groupBy(function ($transaction) {
            $age = now()->diffInHours($transaction->created_at);
            if ($age < 24) return 'Less than 24 hours';
            if ($age < 48) return '24-48 hours';
            if ($age < 72) return '48-72 hours';
            return 'More than 72 hours';
        });

        $this->newLine();
        $this->info('=== Age Distribution ===');
        foreach ($groupedTransactions as $ageGroup => $transactions) {
            $this->info("{$ageGroup}: {$transactions->count()} transactions");
        }

        $this->newLine();
        $this->info('=== Sample Transactions ===');
        $sampleTransactions = $oldPendingTransactions->take(5);
        foreach ($sampleTransactions as $transaction) {
            $age = now()->diffInHours($transaction->created_at);
            $this->line("  • {$transaction->transaction_id} - {$age}h old - UGX " . number_format($transaction->amount));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('🔍 DRY RUN MODE - No transactions will be modified');
            $this->info('Run without --dry-run to actually perform the cleanup');
            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with cleaning up these transactions?')) {
            $this->info('Cleanup cancelled');
            return 0;
        }

        $this->newLine();
        $this->info('🔄 Starting cleanup process...');

        $cleaned = 0;
        $errors = 0;

        foreach ($oldPendingTransactions as $transaction) {
            try {
                $oldStatus = $transaction->status;
                
                // Mark as failed with cleanup reason
                $transaction->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'payment_details' => array_merge(
                        $transaction->payment_details ?? [],
                        [
                            'cleanup_reason' => 'Automated cleanup of old pending transaction',
                            'cleanup_timestamp' => now(),
                            'cleanup_age_hours' => now()->diffInHours($transaction->created_at),
                            'original_status' => $oldStatus,
                        ]
                    ),
                ]);

                $this->line("  ✅ Cleaned up: {$transaction->transaction_id}");
                $cleaned++;

                // Log the cleanup
                Log::info('Old pending transaction cleaned up', [
                    'transaction_id' => $transaction->transaction_id,
                    'old_status' => $oldStatus,
                    'new_status' => 'failed',
                    'age_hours' => now()->diffInHours($transaction->created_at),
                    'amount' => $transaction->amount,
                    'tenant_id' => $transaction->tenant_id,
                    'cleanup_timestamp' => now(),
                ]);

            } catch (\Exception $e) {
                $this->error("  ❌ Error cleaning up {$transaction->transaction_id}: " . $e->getMessage());
                $errors++;

                Log::error('Error during transaction cleanup', [
                    'transaction_id' => $transaction->transaction_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Cleanup Summary ===');
        $this->info("Total found: {$oldPendingTransactions->count()}");
        $this->info("Successfully cleaned: {$cleaned}");
        $this->info("Errors: {$errors}");
        $this->info("Age threshold: {$olderThan} hours");

        // Log summary
        Log::info('Pending payments cleanup completed', [
            'total_found' => $oldPendingTransactions->count(),
            'cleaned' => $cleaned,
            'errors' => $errors,
            'age_threshold_hours' => $olderThan,
            'timestamp' => now(),
        ]);

        if ($cleaned > 0) {
            $this->newLine();
            $this->info('💡 Recommendation: Consider reviewing your payment gateway configuration');
            $this->info('   if you frequently have transactions stuck in pending status.');
        }

        return 0;
    }
} 