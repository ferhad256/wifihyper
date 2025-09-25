<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\VoucherTransaction;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:retry-failed {--limit=10 : Maximum number of failed SMS to retry} {--hours=24 : Only retry SMS failed within the last N hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed SMS messages for completed transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $hours = $this->option('hours');
        
        $this->info("Retrying failed SMS messages (limit: {$limit}, within last {$hours} hours)");
        
        // Find transactions with failed SMS attempts
        $failedTransactions = Transaction::whereHas('voucherTransaction', function ($query) use ($hours) {
            $query->where('sms_sent', false)
                  ->where('sms_attempts', '>', 0)
                  ->where('created_at', '>=', now()->subHours($hours));
        })
        ->where('status', 'completed')
        ->with(['voucherTransaction', 'voucher'])
        ->limit($limit)
        ->get();

        if ($failedTransactions->isEmpty()) {
            $this->info('No failed SMS messages found to retry.');
            return 0;
        }

        $this->info("Found {$failedTransactions->count()} transactions with failed SMS attempts");
        
        $smsService = new SmsService();
        $successCount = 0;
        $failureCount = 0;

        foreach ($failedTransactions as $transaction) {
            $this->info("Retrying SMS for transaction: {$transaction->transaction_id}");
            
            try {
                $result = $smsService->sendVoucherCode(
                    $transaction->phone_number,
                    $transaction->voucher->code,
                    $transaction->voucher->package
                );

                if ($result['success']) {
                    $successCount++;
                    $this->info("✓ SMS sent successfully for {$transaction->transaction_id}");
                    
                    // Update voucher transaction
                    $voucherTransaction = $transaction->voucherTransaction;
                    $voucherTransaction->markSmsSent();
                    
                    // Mark voucher as used
                    $transaction->voucher->update([
                        'status' => 'used',
                        'used_at' => now(),
                        'phone_number' => $transaction->phone_number,
                    ]);
                } else {
                    $failureCount++;
                    $this->error("✗ SMS failed for {$transaction->transaction_id}: {$result['message']}");
                    
                    // Record the retry attempt
                    $transaction->voucherTransaction->recordSmsAttempt($result['message']);
                }
            } catch (\Exception $e) {
                $failureCount++;
                $this->error("✗ Exception for {$transaction->transaction_id}: {$e->getMessage()}");
                
                // Record the retry attempt
                $transaction->voucherTransaction->recordSmsAttempt($e->getMessage());
            }
        }

        $this->info("Retry completed: {$successCount} successful, {$failureCount} failed");
        
        Log::info('SMS retry command completed', [
            'total_processed' => $failedTransactions->count(),
            'successful' => $successCount,
            'failed' => $failureCount,
            'limit' => $limit,
            'hours' => $hours
        ]);

        return 0;
    }
}
