<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\AtomicSmsService;
use Illuminate\Support\Facades\Process;

class TestConcurrentSmsCommand extends Command
{
    protected $signature = 'sms:test-concurrent {phone_number}';
    protected $description = 'Test concurrent SMS attempts to verify atomic locking';

    public function handle()
    {
        $phoneNumber = $this->argument('phone_number');
        
        $this->info("🧪 Testing Concurrent SMS Deduplication");
        $this->info("📱 Phone: {$phoneNumber}");
        $this->newLine();

        // Find a recent completed transaction
        $transaction = Transaction::where('phone_number', $phoneNumber)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (!$transaction) {
            $this->error("❌ No completed transaction found for {$phoneNumber}");
            return 1;
        }

        $this->info("📊 Found Transaction: {$transaction->transaction_id}");
        $this->info("🎫 Voucher: {$transaction->voucher->code}");
        $this->newLine();

        // Check current SMS status
        $atomicService = new AtomicSmsService();
        $status = $atomicService->getSmsStatus($transaction);
        
        if (!$status['sms_sent']) {
            $this->error("❌ SMS not sent yet. Please run payment simulation first.");
            return 1;
        }

        $this->info("📈 Current SMS Status:");
        $this->info("   SMS Sent: ✅ Yes");
        $this->info("   SMS Sent At: " . $status['sms_sent_at']);
        $this->info("   SMS Attempts: " . $status['sms_attempts']);
        $this->newLine();

        $this->info("🔄 Testing concurrent SMS attempts...");
        
        // Simulate concurrent attempts by running multiple processes
        $processes = [];
        $numProcesses = 5;
        
        for ($i = 0; $i < $numProcesses; $i++) {
            $processes[] = Process::start("php artisan sms:test-atomic {$phoneNumber}");
        }
        
        $this->info("🚀 Started {$numProcesses} concurrent processes...");
        
        // Wait for all processes to complete
        $results = [];
        foreach ($processes as $index => $process) {
            $result = $process->wait();
            $results[] = [
                'process' => $index + 1,
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput()
            ];
        }
        
        $this->newLine();
        $this->info("📊 Concurrent Test Results:");
        
        $successCount = 0;
        $duplicateCount = 0;
        
        foreach ($results as $result) {
            $this->info("Process {$result['process']}: Exit Code {$result['exit_code']}");
            
            if (strpos($result['output'], 'Deduplication working') !== false) {
                $duplicateCount++;
                $this->info("   ✅ Correctly blocked as duplicate");
            } elseif (strpos($result['output'], 'SMS sent successfully') !== false) {
                $successCount++;
                $this->info("   ❌ SMS was sent (should not happen)");
            } else {
                $this->info("   ⚠️  Unexpected result");
            }
        }
        
        $this->newLine();
        $this->info("📈 Summary:");
        $this->info("   Total Processes: {$numProcesses}");
        $this->info("   Duplicates Blocked: {$duplicateCount}");
        $this->info("   SMS Sent: {$successCount}");
        
        if ($successCount === 0 && $duplicateCount === $numProcesses) {
            $this->info("✅ All concurrent attempts were correctly blocked!");
            $this->info("   Atomic locking is working perfectly!");
        } else {
            $this->error("❌ Some SMS were sent when they shouldn't have been!");
            $this->error("   Atomic locking may have issues!");
        }

        return 0;
    }
}