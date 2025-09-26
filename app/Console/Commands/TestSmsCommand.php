<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UgSmsService;
use App\Models\Package;

class TestSmsCommand extends Command
{
    protected $signature = 'sms:test {phone_number} {--voucher=TEST123} {--package=1}';
    protected $description = 'Test UG SMS service';

    public function handle()
    {
        $phoneNumber = $this->argument('phone_number');
        $voucherCode = $this->option('voucher');
        $packageId = $this->option('package');
        
        $this->info("🧪 Testing UG SMS Service");
        $this->info("📱 Phone: {$phoneNumber}");
        $this->info("🎫 Voucher: {$voucherCode}");
        $this->newLine();

        $smsService = new UgSmsService();

        // Check if service is configured
        if (!$smsService->isConfigured()) {
            $this->error("❌ UG SMS service is not configured");
            $this->newLine();
            $this->info("📋 Required Environment Variables:");
            $this->info("   UG_SMS_USERNAME=your_username");
            $this->info("   UG_SMS_PASSWORD=your_password");
            $this->info("   UG_SMS_BASE_URL=https://ugsms.com/v1/sms/send (optional)");
            return 1;
        }

        $this->info("✅ UG SMS service is configured");
        $this->newLine();

        // Get package info
        $package = Package::find($packageId);
        if (!$package) {
            $this->warn("⚠️  Package ID {$packageId} not found, using default package info");
        }

        // Send test SMS
        $this->info("📱 Sending test SMS...");
        $startTime = microtime(true);
        
        $result = $smsService->sendVoucherCode($phoneNumber, $voucherCode, $package);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        $this->newLine();
        
        if ($result['success']) {
            $this->info("✅ SMS sent successfully!");
            $this->info("   Duration: {$duration}ms");
            $this->info("   Response: " . json_encode($result['data'] ?? []));
        } else {
            $this->error("❌ SMS failed!");
            $this->error("   Error: {$result['message']}");
            $this->error("   Duration: {$duration}ms");
        }

        $this->newLine();
        $this->info("📊 Test completed");
        $this->info("📱 Check your phone ({$phoneNumber}) for the SMS");

        return $result['success'] ? 0 : 1;
    }
}