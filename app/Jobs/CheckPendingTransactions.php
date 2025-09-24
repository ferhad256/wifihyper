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

class CheckPendingTransactions implements ShouldQueue
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
            Log::info('CheckPendingTransactions: Starting job execution');

            $jpesaService = new JpesaService();
            
            // Get pending transactions older than 5 minutes
            $pendingTransactions = Transaction::where('status', 'pending')
                ->where('created_at', '<=', now()->subMinutes(5))
                ->orderBy('created_at', 'asc')
                ->limit(50) // Process max 50 transactions per run
                ->get();

            if ($pendingTransactions->isEmpty()) {
                Log::info('CheckPendingTransactions: No pending transactions found');
                return;
            }

            Log::info('CheckPendingTransactions: Found pending transactions', [
                'count' => $pendingTransactions->count()
            ]);

            $updated = 0;
            $failed = 0;
            $unchanged = 0;

            foreach ($pendingTransactions as $transaction) {
                try {
                    Log::info('CheckPendingTransactions: Checking transaction', [
                        'transaction_id' => $transaction->transaction_id,
                        'created_at' => $transaction->created_at,
                        'age_minutes' => $transaction->created_at->diffInMinutes(now())
                    ]);

                    $result = $jpesaService->checkAndUpdateTransactionStatus($transaction);

                    if ($result['success']) {
                        if (isset($result['new_status'])) {
                            Log::info('CheckPendingTransactions: Transaction status updated', [
                                'transaction_id' => $transaction->transaction_id,
                                'old_status' => $result['old_status'],
                                'new_status' => $result['new_status'],
                                'jpesa_status' => $result['jpesa_status'] ?? 'unknown'
                            ]);
                            $updated++;
                        } else {
                            Log::info('CheckPendingTransactions: Transaction status unchanged', [
                                'transaction_id' => $transaction->transaction_id,
                                'status' => $result['status'],
                                'message' => $result['message']
                            ]);
                            $unchanged++;
                        }
                    } else {
                        Log::error('CheckPendingTransactions: Failed to check transaction status', [
                            'transaction_id' => $transaction->transaction_id,
                            'error' => $result['message'],
                            'error_code' => $result['error_code'] ?? 'UNKNOWN_ERROR'
                        ]);
                        $failed++;
                    }

                } catch (\Exception $e) {
                    Log::error('CheckPendingTransactions: Exception while checking transaction', [
                        'transaction_id' => $transaction->transaction_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failed++;
                }

                // Add small delay between requests to avoid overwhelming the API
                usleep(500000); // 0.5 seconds
            }

            Log::info('CheckPendingTransactions: Job completed', [
                'updated' => $updated,
                'unchanged' => $unchanged,
                'failed' => $failed,
                'total_processed' => $pendingTransactions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('CheckPendingTransactions: Job failed with exception', [
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
        Log::error('CheckPendingTransactions: Job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
