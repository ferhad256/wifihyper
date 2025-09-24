<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\LongPendingTransactionService;
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

            $longPendingService = new LongPendingTransactionService();
            
            // Get timeout from config
            $pendingTimeoutMinutes = config('services.jpesa.pending_timeout_minutes', 15);
            
            // Process long pending transactions
            $results = $longPendingService->processLongPendingTransactions($pendingTimeoutMinutes);

            Log::info('CheckLongPendingTransactions: Job completed', [
                'results' => $results,
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
