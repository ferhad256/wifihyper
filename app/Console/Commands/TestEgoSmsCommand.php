<?php

namespace App\Console\Commands;

use App\Services\EgoSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEgoSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test-ego {phoneNumber} {--voucher=TEST123} {--package=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the EgoSMS service by sending a voucher code.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phoneNumber = $this->argument('phoneNumber');
        $voucherCode = $this->option('voucher');
        $packageId = $this->option('package');

        $this->info("🧪 Testing EgoSMS Service");
        $this->info("📱 Phone: {$phoneNumber}");
        $this->info("🎫 Voucher: {$voucherCode}");

        $smsService = new EgoSmsService();

        if (!$smsService->isConfigured()) {
            $this->error("❌ EgoSMS service is NOT configured. Please set EGO_SMS_USERNAME, EGO_SMS_PASSWORD, and EGO_SMS_BASE_URL in your .env file.");
            return 1;
        }

        $this->info("✅ EgoSMS service is configured");
        $this->newLine();

        // Get package if specified
        $package = null;
        if ($packageId) {
            $package = \App\Models\Package::find($packageId);
            if ($package) {
                $this->info("📦 Package: {$package->name}");
            }
        }

        $this->info("📱 Sending test SMS...");
        $startTime = microtime(true);
        $result = $smsService->sendVoucherCode($phoneNumber, $voucherCode, $package);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        if ($result['success']) {
            $this->info("✅ SMS sent successfully!");
            $this->info("   Duration: {$duration}ms");
            $this->info("   Response: " . json_encode($result['data']));
        } else {
            $this->error("❌ SMS failed!");
            $this->error("   Error: {$result['message']}");
            $this->error("   Duration: {$duration}ms");
            if (isset($result['attempts_made'])) {
                $this->error("   Attempts: {$result['attempts_made']}");
            }
        }

        $this->newLine();
        $this->info("📊 Test completed");
        $this->info("📱 Check your phone ({$phoneNumber}) for the SMS");

        return 0;
    }
}
