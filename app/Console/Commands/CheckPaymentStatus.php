<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CheckPaymentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:status {--tenant-id=} {--recent} {--failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check payment status and recent transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking Payment Status...');

        $tenantId = $this->option('tenant-id');
        $recent = $this->option('recent');
        $failed = $this->option('failed');

        try {
            // Get transactions query
            $query = Transaction::with(['tenant', 'hotspot', 'package']);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            if ($recent) {
                $query->where('created_at', '>=', now()->subDays(7));
            }

            if ($failed) {
                $query->where('status', 'failed');
            }

            $transactions = $query->orderBy('created_at', 'desc')->get();

            if ($transactions->isEmpty()) {
                $this->warn('❌ No transactions found with the specified criteria');
                return 0;
            }

            $this->info("📊 Found {$transactions->count()} transactions");

            // Group by status
            $statusCounts = $transactions->groupBy('status')->map->count();
            
            $this->info("\n📈 Transaction Status Summary:");
            foreach ($statusCounts as $status => $count) {
                $icon = $this->getStatusIcon($status);
                $this->info("   {$icon} {$status}: {$count}");
            }

            // Show recent transactions
            $this->info("\n💳 Recent Transactions:");
            $this->table(
                ['ID', 'Status', 'Amount', 'Phone', 'Created', 'Details'],
                $transactions->take(10)->map(function ($transaction) {
                    return [
                        $transaction->transaction_id,
                        $this->getStatusIcon($transaction->status) . ' ' . $transaction->status,
                        'UGX ' . number_format($transaction->amount),
                        $transaction->phone_number,
                        $transaction->created_at->format('M d, H:i'),
                        $this->getTransactionDetails($transaction)
                    ];
                })
            );

            // Show failed transactions if any
            $failedTransactions = $transactions->where('status', 'failed');
            if ($failedTransactions->isNotEmpty()) {
                $this->error("\n❌ Failed Transactions Details:");
                foreach ($failedTransactions->take(5) as $transaction) {
                    $this->error("   Transaction: {$transaction->transaction_id}");
                    $this->error("   Amount: UGX {$transaction->amount}");
                    $this->error("   Phone: {$transaction->phone_number}");
                    $this->error("   Created: {$transaction->created_at->format('M d, H:i')}");
                    
                    if (isset($transaction->payment_details['yo_payments_error'])) {
                        $this->error("   Error: {$transaction->payment_details['yo_payments_error']}");
                    }
                    
                    $this->error("   ---");
                }
            }

            // Database connection test
            $this->info("\n🔌 Database Connection Test:");
            try {
                DB::connection()->getPdo();
                $this->info("   ✅ Database connection successful");
            } catch (\Exception $e) {
                $this->error("   ❌ Database connection failed: " . $e->getMessage());
            }

            // Check log file
            $this->info("\n📋 Log File Check:");
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $size = filesize($logFile);
                $this->info("   ✅ Log file exists: " . number_format($size) . " bytes");
                
                // Check for recent payment logs
                $lines = file($logFile);
                $recentLines = array_slice($lines, -100);
                
                $paymentLogs = 0;
                foreach ($recentLines as $line) {
                    if (strpos($line, 'YoPaymentsService') !== false || 
                        strpos($line, 'Payment') !== false) {
                        $paymentLogs++;
                    }
                }
                
                $this->info("   📝 Recent payment logs: {$paymentLogs}");
            } else {
                $this->warn("   ⚠️  Log file not found at: {$logFile}");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("💥 Error checking payment status: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get status icon
     */
    private function getStatusIcon($status)
    {
        return match($status) {
            'completed' => '✅',
            'pending' => '⏳',
            'failed' => '❌',
            'cancelled' => '🚫',
            default => '❓'
        };
    }

    /**
     * Get transaction details
     */
    private function getTransactionDetails($transaction)
    {
        $details = [];
        
        if ($transaction->tenant) {
            $details[] = "Tenant: " . substr($transaction->tenant->business_name, 0, 20);
        }
        
        if ($transaction->hotspot) {
            $details[] = "Hotspot: " . substr($transaction->hotspot->name, 0, 15);
        }
        
        if ($transaction->package) {
            $details[] = "Package: " . substr($transaction->package->name, 0, 15);
        }
        
        return implode(', ', $details);
    }
} 