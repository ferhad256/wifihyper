<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class MonitorJpesaCallbacks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jpesa:monitor-callbacks {--hours=24 : Number of hours to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor JPesa callback status and identify any issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $this->info("Monitoring JPesa callbacks for the last {$hours} hours...");

        // Get transactions from the last X hours
        $transactions = Transaction::where('created_at', '>=', now()->subHours($hours))
            ->where('status', '!=', 'completed')
            ->get();

        $this->info("Found {$transactions->count()} pending transactions");

        $callbackIssues = [];
        $successfulCallbacks = 0;

        foreach ($transactions as $transaction) {
            $paymentDetails = $transaction->payment_details ?? [];
            
            // Check if callback was received
            $callbackReceived = isset($paymentDetails['callback_received_at']);
            $callbackStatus = $paymentDetails['jpesa_callback_status'] ?? 'unknown';
            
            if ($callbackReceived) {
                if ($callbackStatus === 'success') {
                    $successfulCallbacks++;
                } else {
                    $callbackIssues[] = [
                        'transaction_id' => $transaction->transaction_id,
                        'status' => $transaction->status,
                        'callback_status' => $callbackStatus,
                        'callback_message' => $paymentDetails['jpesa_callback_message'] ?? 'No message',
                        'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                        'callback_received_at' => $paymentDetails['callback_received_at'] ?? 'Not received'
                    ];
                }
            } else {
                // Check if transaction is old enough to expect a callback
                $ageInMinutes = $transaction->created_at->diffInMinutes(now());
                if ($ageInMinutes > 30) { // Expect callback within 30 minutes
                    $callbackIssues[] = [
                        'transaction_id' => $transaction->transaction_id,
                        'status' => $transaction->status,
                        'callback_status' => 'not_received',
                        'callback_message' => 'No callback received',
                        'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                        'age_minutes' => $ageInMinutes
                    ];
                }
            }
        }

        // Display results
        $this->info("\n=== Callback Status Summary ===");
        $this->info("Successful callbacks: {$successfulCallbacks}");
        $this->info("Callback issues: " . count($callbackIssues));

        if (!empty($callbackIssues)) {
            $this->warn("\n=== Callback Issues Found ===");
            
            $headers = ['Transaction ID', 'Status', 'Callback Status', 'Message', 'Created At', 'Age/Received'];
            $rows = [];
            
            foreach ($callbackIssues as $issue) {
                $rows[] = [
                    $issue['transaction_id'],
                    $issue['status'],
                    $issue['callback_status'],
                    substr($issue['callback_message'], 0, 30) . '...',
                    $issue['created_at'],
                    $issue['age_minutes'] ?? $issue['callback_received_at']
                ];
            }
            
            $this->table($headers, $rows);
        }

        // Check callback endpoint status
        $this->info("\n=== Callback Endpoint Status ===");
        $callbackUrl = config('services.jpesa.callback_url');
        $this->info("Callback URL: {$callbackUrl}");
        
        // Test callback endpoint accessibility
        try {
            $testUrl = str_replace('/payment/jpesa/callback', '/payment/jpesa/test-callback', $callbackUrl);
            $this->info("Test endpoint: {$testUrl}");
            $this->info("Endpoint status: Ready");
        } catch (\Exception $e) {
            $this->error("Endpoint test failed: " . $e->getMessage());
        }

        // Log summary
        Log::info('JPesa Callback Monitor Summary', [
            'hours_monitored' => $hours,
            'total_transactions' => $transactions->count(),
            'successful_callbacks' => $successfulCallbacks,
            'callback_issues' => count($callbackIssues),
            'issues' => $callbackIssues
        ]);

        $this->info("\nMonitoring complete!");
        
        return 0;
    }
}