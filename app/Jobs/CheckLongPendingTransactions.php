<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\JpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckLongPendingTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('CheckLongPendingTransactions: Starting job execution');

            $jpesaService = new JpesaService();
            
            // Get pending transactions older than 15 minutes (configurable)
            $pendingTimeoutMinutes = config('services.jpesa.pending_timeout_minutes', 15);
            $pendingTransactions = Transaction::where('status', 'pending')
                ->where('created_at', '<=', now()->subMinutes($pendingTimeoutMinutes))
                ->orderBy('created_at', 'asc')
                ->limit(100) // Process max 100 transactions per run
                ->get();

            if ($pendingTransactions->isEmpty()) {
                Log::info('CheckLongPendingTransactions: No long-pending transactions found', [
                    'timeout_minutes' => $pendingTimeoutMinutes
                ]);
                return;
            }

            Log::info('CheckLongPendingTransactions: Found long-pending transactions', [
                'count' => $pendingTransactions->count(),
                'timeout_minutes' => $pendingTimeoutMinutes
            ]);

            $updated = 0;
            $failed = 0;
            $unchanged = 0;
            $markedAsFailed = 0;

            foreach ($pendingTransactions as $transaction) {
                try {
                    Log::info('CheckLongPendingTransactions: Checking long-pending transaction', [
                        'transaction_id' => $transaction->transaction_id,
                        'created_at' => $transaction->created_at,
                        'age_minutes' => $transaction->created_at->diffInMinutes(now())
                    ]);

                    $result = $jpesaService->checkAndUpdateTransactionStatus($transaction);

                    if ($result['success']) {
                        if (isset($result['new_status'])) {
                            if ($result['new_status'] === 'failed' && isset($result['reason'])) {
                                Log::info('CheckLongPendingTransactions: Transaction marked as failed', [
                                    'transaction_id' => $transaction->transaction_id,
                                    'old_status' => $result['old_status'],
                                    'new_status' => $result['new_status'],
                                    'reason' => $result['reason']
                                ]);
                                $markedAsFailed++;
                            } else {
                                Log::info('CheckLongPendingTransactions: Transaction status updated', [
                                    'transaction_id' => $transaction->transaction_id,
                                    'old_status' => $result['old_status'],
                                    'new_status' => $result['new_status'],
                                    'jpesa_status' => $result['jpesa_status'] ?? 'unknown'
                                ]);
                                $updated++;
                            }
                        } else {
                            Log::info('CheckLongPendingTransactions: Transaction status unchanged', [
                                'transaction_id' => $transaction->transaction_id,
                                'status' => $result['status'],
                                'message' => $result['message']
                            ]);
                            $unchanged++;
                        }
                    } else {
                        Log::error('CheckLongPendingTransactions: Failed to check transaction status', [
                            'transaction_id' => $transaction->transaction_id,
                            'error' => $result['message'],
                            'error_code' => $result['error_code'] ?? 'UNKNOWN_ERROR'
                        ]);
                        $failed++;
                    }

                } catch (\Exception $e) {
                    Log::error('CheckLongPendingTransactions: Exception while checking transaction', [
                        'transaction_id' => $transaction->transaction_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failed++;
                }

                // Add small delay between requests to avoid overwhelming the API
                usleep(500000); // 0.5 seconds
            }

            Log::info('CheckLongPendingTransactions: Job completed', [
                'updated' => $updated,
                'marked_as_failed' => $markedAsFailed,
                'unchanged' => $unchanged,
                'failed' => $failed,
                'total_processed' => $pendingTransactions->count(),
                'timeout_minutes' => $pendingTimeoutMinutes
            ]);

        } catch (\Exception $e) {
            Log::error('CheckLongPendingTransactions: Job failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CheckLongPendingTransactions: Job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
