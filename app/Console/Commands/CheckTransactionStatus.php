<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\JpesaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTransactionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transaction:check-status 
                            {--transaction-id= : Specific transaction ID to check}
                            {--all-pending : Check all pending transactions}
                            {--older-than=5 : Only check transactions older than X minutes}
                            {--dry-run : Show what would be checked without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check transaction status using JPesa API when IPN is missing or delayed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Transaction Status Checker');
        $this->newLine();

        $jpesaService = new JpesaService();
        $transactionId = $this->option('transaction-id');
        $checkAllPending = $this->option('all-pending');
        $olderThanMinutes = (int) $this->option('older-than');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            if ($transactionId) {
                // Check specific transaction
                $this->checkSpecificTransaction($jpesaService, $transactionId, $dryRun);
            } elseif ($checkAllPending) {
                // Check all pending transactions
                $this->checkAllPendingTransactions($jpesaService, $olderThanMinutes, $dryRun);
            } else {
                $this->error('❌ Please specify either --transaction-id or --all-pending');
                return 1;
            }

            $this->newLine();
            $this->info('✅ Transaction status check completed!');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Transaction status check command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Check a specific transaction
     */
    private function checkSpecificTransaction(JpesaService $jpesaService, string $transactionId, bool $dryRun)
    {
        $this->info("🔍 Checking specific transaction: {$transactionId}");
        $this->newLine();

        $transaction = Transaction::where('transaction_id', $transactionId)->first();

        if (!$transaction) {
            $this->error("❌ Transaction not found: {$transactionId}");
            return;
        }

        $this->displayTransactionInfo($transaction);

        if ($transaction->status !== 'pending') {
            $this->warn("⚠️ Transaction is not pending (status: {$transaction->status}), skipping status check");
            return;
        }

        if ($dryRun) {
            $this->info("🔍 DRY RUN: Would check status for transaction {$transactionId}");
            return;
        }

        $result = $jpesaService->checkAndUpdateTransactionStatus($transaction);

        if ($result['success']) {
            if (isset($result['new_status'])) {
                $this->info("✅ Transaction status updated: {$result['old_status']} → {$result['new_status']}");
                if (isset($result['jpesa_status'])) {
                    $this->line("📊 JPesa Status: {$result['jpesa_status']}");
                }
            } else {
                $this->info("ℹ️ {$result['message']}");
            }
        } else {
            $this->error("❌ Failed to check transaction status: {$result['message']}");
        }
    }

    /**
     * Check all pending transactions
     */
    private function checkAllPendingTransactions(JpesaService $jpesaService, int $olderThanMinutes, bool $dryRun)
    {
        $this->info("🔍 Checking all pending transactions older than {$olderThanMinutes} minutes");
        $this->newLine();

        $pendingTransactions = Transaction::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingTransactions->isEmpty()) {
            $this->info("ℹ️ No pending transactions found older than {$olderThanMinutes} minutes");
            return;
        }

        $this->info("📊 Found {$pendingTransactions->count()} pending transactions to check");
        $this->newLine();

        $updated = 0;
        $failed = 0;
        $unchanged = 0;

        foreach ($pendingTransactions as $transaction) {
            $this->line("🔍 Checking: {$transaction->transaction_id} (Created: {$transaction->created_at->diffForHumans()})");

            if ($dryRun) {
                $this->info("🔍 DRY RUN: Would check status for transaction {$transaction->transaction_id}");
                continue;
            }

            $result = $jpesaService->checkAndUpdateTransactionStatus($transaction);

            if ($result['success']) {
                if (isset($result['new_status'])) {
                    $this->info("✅ Updated: {$result['old_status']} → {$result['new_status']}");
                    $updated++;
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
            $this->line("ℹ️ Unchanged: {$unchanged}");
            $this->line("❌ Failed: {$failed}");
        }
    }

    /**
     * Display transaction information
     */
    private function displayTransactionInfo(Transaction $transaction)
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['Transaction ID', $transaction->transaction_id],
                ['Status', $transaction->status],
                ['Amount', 'UGX ' . number_format($transaction->amount)],
                ['Phone Number', $transaction->phone_number],
                ['Created At', $transaction->created_at->format('Y-m-d H:i:s')],
                ['Age', $transaction->created_at->diffForHumans()],
                ['JPesa Reference', $transaction->jpesa_reference ?? 'N/A'],
                ['Tenant', $transaction->tenant->name ?? 'N/A'],
                ['Package', $transaction->package->name ?? 'N/A'],
            ]
        );
    }
}
