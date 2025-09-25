<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use App\Services\UgSmsService;
use App\Services\BackupSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test {phone_number} {--message= : Custom message to send} {--service= : Test specific service (primary|backup|both)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMS service with improved retry logic and backup fallback';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phoneNumber = $this->argument('phone_number');
        $customMessage = $this->option('message');
        $service = $this->option('service') ?? 'both';
        
        $this->info("Testing SMS service to: {$phoneNumber}");
        $this->info("Service mode: {$service}");
        
        if ($customMessage) {
            $this->info("Custom message: {$customMessage}");
        }
        
        // Test message
        $testMessage = $customMessage ?: "Test SMS from WifiHyper - Improved SMS system with retry logic and backup service. Time: " . now()->format('Y-m-d H:i:s');
        
        $this->info("Sending test message...");
        $this->newLine();
        
        if ($service === 'primary' || $service === 'both') {
            $this->testPrimaryService($phoneNumber, $testMessage);
        }
        
        if ($service === 'backup' || $service === 'both') {
            $this->testBackupService($phoneNumber, $testMessage);
        }
        
        if ($service === 'both') {
            $this->testComprehensiveService($phoneNumber, $testMessage);
        }
        
        $this->newLine();
        $this->info("SMS testing completed!");
        
        return 0;
    }
    
    /**
     * Test primary SMS service
     */
    protected function testPrimaryService($phoneNumber, $message)
    {
        $this->info("🔵 Testing PRIMARY SMS Service (UG SMS)...");
        
        $primaryService = new UgSmsService();
        
        $startTime = microtime(true);
        $result = $primaryService->sendVoucherCode($phoneNumber, 'TEST123', null);
        $endTime = microtime(true);
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        if ($result['success']) {
            $this->info("✅ Primary SMS sent successfully!");
            $this->info("   Duration: {$duration}ms");
            $this->info("   Attempts: " . ($result['attempts_made'] ?? 1));
        } else {
            $this->error("❌ Primary SMS failed!");
            $this->error("   Error: {$result['message']}");
            $this->error("   Attempts: " . ($result['attempts_made'] ?? 0));
        }
        
        $this->newLine();
    }
    
    /**
     * Test backup SMS service
     */
    protected function testBackupService($phoneNumber, $message)
    {
        $this->info("🟡 Testing BACKUP SMS Service...");
        
        $backupService = new BackupSmsService();
        
        if (!$backupService->isEnabled()) {
            $this->warn("⚠️  Backup SMS service not enabled or configured");
            $this->warn("   Set BACKUP_SMS_ENABLED=true and configure credentials");
            $this->newLine();
            return;
        }
        
        $startTime = microtime(true);
        $result = $backupService->sendVoucherCode($phoneNumber, 'TEST123', null);
        $endTime = microtime(true);
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        if ($result['success']) {
            $this->info("✅ Backup SMS sent successfully!");
            $this->info("   Duration: {$duration}ms");
            $this->info("   Service: " . ($result['service'] ?? 'backup'));
        } else {
            $this->error("❌ Backup SMS failed!");
            $this->error("   Error: {$result['message']}");
        }
        
        $this->newLine();
    }
    
    /**
     * Test comprehensive SMS service with fallback
     */
    protected function testComprehensiveService($phoneNumber, $message)
    {
        $this->info("🟢 Testing COMPREHENSIVE SMS Service (with fallback)...");
        
        $smsService = new SmsService();
        
        // Get service status
        $status = $smsService->getServiceStatus();
        $this->info("   Primary enabled: " . ($status['primary_enabled'] ? 'Yes' : 'No'));
        $this->info("   Backup enabled: " . ($status['backup_enabled'] ? 'Yes' : 'No'));
        
        $startTime = microtime(true);
        $result = $smsService->sendVoucherCode($phoneNumber, 'TEST123', null);
        $endTime = microtime(true);
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        if ($result['success']) {
            $this->info("✅ Comprehensive SMS sent successfully!");
            $this->info("   Duration: {$duration}ms");
            $this->info("   Service used: " . ($result['service'] ?? 'primary'));
            $this->info("   Attempts: " . ($result['attempts_made'] ?? 1));
        } else {
            $this->error("❌ Comprehensive SMS failed!");
            $this->error("   Error: {$result['message']}");
            $this->error("   Services tried: " . implode(', ', $result['services_tried'] ?? ['primary']));
        }
        
        $this->newLine();
    }
}