<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\YoPaymentsService;
use Illuminate\Support\Facades\Log;

class TestPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:test {--package-id=} {--phone=} {--amount=} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test payment processing with detailed logging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Starting Payment Test...');
        
        // Get test parameters
        $packageId = $this->option('package-id');
        $phoneNumber = $this->option('phone') ?: '256700000000';
        $amount = $this->option('amount') ?: 1000;
        $debug = $this->option('debug');

        if ($debug) {
            $this->info('🐛 Debug mode enabled - will show detailed logs');
        }

        $this->info("📱 Phone: {$phoneNumber}");
        $this->info("💰 Amount: UGX {$amount}");

        try {
            // Find or create a test package
            if ($packageId) {
                $package = Package::find($packageId);
                if (!$package) {
                    $this->error("❌ Package with ID {$packageId} not found");
                    return 1;
                }
            } else {
                // Create a test package
                $package = Package::first();
                if (!$package) {
                    $this->error("❌ No packages found in database");
                    return 1;
                }
            }

            $this->info("📦 Package: {$package->name} (ID: {$package->id})");

            // Find or create a test tenant
            $tenant = Tenant::first();
            if (!$tenant) {
                $this->error("❌ No tenants found in database");
                return 1;
            }

            $this->info("🏢 Tenant: {$tenant->business_name} (ID: {$tenant->id})");

            // Create a test transaction
            $transaction = Transaction::create([
                'tenant_id' => $tenant->id,
                'hotspot_id' => $package->hotspot_id,
                'package_id' => $package->id,
                'transaction_id' => 'TEST_' . time() . '_' . rand(1000, 9999),
                'amount' => $amount,
                'transaction_fee' => 0,
                'net_amount' => $amount,
                'currency' => 'UGX',
                'status' => 'pending',
                'phone_number' => $phoneNumber,
                'payment_details' => [
                    'test_mode' => true,
                    'created_at' => now()->toISOString()
                ]
            ]);

            $this->info("💳 Test transaction created: {$transaction->transaction_id}");

            // Test Yo Payments service
            $this->info("🚀 Testing Yo Payments service...");
            
            $yoPayments = new YoPaymentsService();
            
            // Test configuration
            $this->info("⚙️  Yo Payments Configuration:");
            $this->info("   - Base URL: " . $yoPayments->baseUrl);
            $this->info("   - Username: " . $yoPayments->username);
            $this->info("   - Public Key Enabled: " . ($yoPayments->publicKeyEnabled ? 'Yes' : 'No'));
            $this->info("   - Private Key Path: " . ($yoPayments->privateKeyPath ?: 'Not set'));

            // Test payment initiation
            $this->info("💸 Initiating test payment...");
            
            $result = $yoPayments->initiatePayment($transaction, $phoneNumber);

            $this->info("📊 Payment Result:");
            $this->info("   - Success: " . ($result['success'] ? 'Yes' : 'No'));
            $this->info("   - Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['transaction_reference'])) {
                $this->info("   - Reference: " . $result['transaction_reference']);
            }

            if (isset($result['data'])) {
                $this->info("   - Data: " . json_encode($result['data'], JSON_PRETTY_PRINT));
            }

            // Show recent logs if debug mode
            if ($debug) {
                $this->info("\n📋 Recent Payment Logs:");
                $this->showRecentLogs();
            }

            // Clean up test transaction
            $transaction->delete();
            $this->info("🧹 Test transaction cleaned up");

            if ($result['success']) {
                $this->info("✅ Payment test completed successfully!");
                return 0;
            } else {
                $this->error("❌ Payment test failed!");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("💥 Payment test failed with exception: " . $e->getMessage());
            
            if ($debug) {
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Show recent payment-related logs
     */
    private function showRecentLogs()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!file_exists($logFile)) {
            $this->warn("   No log file found at: {$logFile}");
            return;
        }

        // Read last 50 lines of log file
        $lines = file($logFile);
        $recentLines = array_slice($lines, -50);
        
        $paymentLogs = [];
        foreach ($recentLines as $line) {
            if (strpos($line, 'YoPaymentsService') !== false || 
                strpos($line, 'Payment initiation') !== false ||
                strpos($line, 'PaymentController') !== false) {
                $paymentLogs[] = trim($line);
            }
        }

        if (empty($paymentLogs)) {
            $this->warn("   No recent payment logs found");
            return;
        }

        // Show last 10 payment logs
        $recentPaymentLogs = array_slice($paymentLogs, -10);
        foreach ($recentPaymentLogs as $log) {
            $this->line("   " . $log);
        }
    }
} 