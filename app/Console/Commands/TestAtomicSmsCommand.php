<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\VoucherTransaction;
use App\Services\AtomicSmsService;
use App\Services\VoucherDeduplicationService;

class TestAtomicSmsCommand extends Command
{
    protected $signature = 'sms:test-atomic {phone_number}';
    protected $description = 'Test atomic SMS deduplication to ensure only 1 SMS per transaction';

    public function handle()
    {
        $phoneNumber = $this->argument('phone_number');
        
        $this->info("🧪 Testing Atomic SMS Deduplication");
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
        
        $this->info("📈 Current SMS Status:");
        $this->info("   SMS Sent: " . ($status['sms_sent'] ? '✅ Yes' : '❌ No'));
        $this->info("   SMS Sent At: " . ($status['sms_sent_at'] ?? 'N/A'));
        $this->info("   SMS Attempts: " . $status['sms_attempts']);
        $this->info("   Last Error: " . ($status['last_error'] ?? 'None'));
        $this->newLine();

        if ($status['sms_sent']) {
            $this->warn("⚠️  SMS already sent! Testing deduplication...");
            
            // Test deduplication by trying to send again
            $this->info("🔄 Attempting to send SMS again (should be blocked)...");
            $result = $atomicService->sendVoucherSmsAtomic($transaction);
            
            if ($result['success'] && isset($result['duplicate']) && $result['duplicate']) {
                $this->info("✅ Deduplication working! SMS was blocked as duplicate");
                $this->info("   Message: {$result['message']}");
            } else {
                $this->error("❌ Deduplication failed! SMS was sent again");
            }
        } else {
            $this->info("📱 SMS not sent yet. Testing atomic SMS sending...");
            
            $startTime = microtime(true);
            $result = $atomicService->sendVoucherSmsAtomic($transaction);
            $endTime = microtime(true);
            
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            if ($result['success']) {
                $this->info("✅ SMS sent successfully!");
                $this->info("   Duration: {$duration}ms");
                $this->info("   Voucher Code: {$result['voucher_code']}");
                $this->info("   Package: {$result['package_name']}");
                $this->info("   SMS Attempts: {$result['sms_attempts']}");
                
                if (isset($result['duplicate']) && $result['duplicate']) {
                    $this->warn("   ⚠️  SMS was already sent (duplicate prevention)");
                }
            } else {
                $this->error("❌ SMS failed!");
                $this->error("   Error: {$result['message']}");
                $this->error("   Attempts: " . ($result['sms_attempts'] ?? 0));
            }
        }

        // Show final status
        $this->newLine();
        $finalStatus = $atomicService->getSmsStatus($transaction);
        $this->info("📊 Final SMS Status:");
        $this->info("   SMS Sent: " . ($finalStatus['sms_sent'] ? '✅ Yes' : '❌ No'));
        $this->info("   SMS Sent At: " . ($finalStatus['sms_sent_at'] ?? 'N/A'));
        $this->info("   SMS Attempts: " . $finalStatus['sms_attempts']);
        $this->info("   Last Error: " . ($finalStatus['last_error'] ?? 'None'));

        return 0;
    }
}