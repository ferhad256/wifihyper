<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\JpesaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLongPendingTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transaction:check-long-pending 
                            {--timeout=15 : Check transactions older than X minutes}
                            {--limit=50 : Maximum number of transactions to check}
                            {--dry-run : Show what would be checked without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check status of transactions that have been pending for too long and mark non-existent ones as failed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Long-Pending Transaction Status Checker');
        $this->newLine();

        $timeoutMinutes = (int) $this->option('timeout');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            $jpesaService = new JpesaService();
            
            // Get pending transactions older than specified minutes
            $pendingTransactions = Transaction::where('status', 'pending')
                ->where('created_at', '<=', now()->subMinutes($timeoutMinutes))
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();

            if ($pendingTransactions->isEmpty()) {
                $this->info("ℹ️ No pending transactions found older than {$timeoutMinutes} minutes");
                return 0;
            }

            $this->info("📊 Found {$pendingTransactions->count()} long-pending transactions to check");
            $this->newLine();

            $updated = 0;
            $failed = 0;
            $unchanged = 0;
            $markedAsFailed = 0;

            foreach ($pendingTransactions as $transaction) {
                $this->line("🔍 Checking: {$transaction->transaction_id} (Created: {$transaction->created_at->diffForHumans()})");

                if ($dryRun) {
                    $this->info("🔍 DRY RUN: Would check status for transaction {$transaction->transaction_id}");
                    continue;
                }

                $result = $jpesaService->checkAndUpdateTransactionStatus($transaction);

                if ($result['success']) {
                    if (isset($result['new_status'])) {
                        if ($result['new_status'] === 'failed' && isset($result['reason'])) {
                            $this->warn("❌ Marked as failed: {$result['reason']}");
                            $markedAsFailed++;
                        } else {
                            $this->info("✅ Updated: {$result['old_status']} → {$result['new_status']}");
                            $updated++;
                        }
                    } else {
                        $this->info("ℹ️ {$result['message']}");
                        $unchanged++;
                    }
                } else {
                    $this->error("❌ Failed: {$result['message']}");
                    $failed++;
                }

                $this->newLine();
            }

            if (!$dryRun) {
                $this->info("📊 Summary:");
                $this->line("✅ Updated: {$updated}");
                $this->line("❌ Marked as Failed: {$markedAsFailed}");
                $this->line("ℹ️ Unchanged: {$unchanged}");
                $this->line("❌ Failed to Check: {$failed}");
            }

            $this->newLine();
            $this->info('✅ Long-pending transaction check completed!');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Long-pending transaction check command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
