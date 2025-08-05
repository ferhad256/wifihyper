<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GatewayTestService;

class TestGateways extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gateways:test {--production : Check production readiness}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test all gateways (SMS, Payment, Email) and production readiness';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testing WIFIHYPER Gateways and Production Readiness');
        $this->newLine();

        if ($this->option('production')) {
            $this->testProductionReadiness();
        } else {
            $this->testGateways();
        }
    }

    /**
     * Test all gateways
     */
    private function testGateways()
    {
        $this->info('📧 Testing Email Gateway (Resend)...');
        $emailResult = GatewayTestService::testEmailGateway();
        $this->displayResult('Email Gateway', $emailResult);

        $this->info('📱 Testing SMS Gateway (UG SMS)...');
        $smsResult = GatewayTestService::testSmsGateway();
        $this->displayResult('SMS Gateway', $smsResult);

        $this->info('💳 Testing Payment Gateway (Yo! Payments)...');
        $paymentResult = GatewayTestService::testPaymentGateway();
        $this->displayResult('Payment Gateway', $paymentResult);

        $this->newLine();
        $this->info('🎯 Gateway Test Summary:');
        $this->displaySummary([$emailResult, $smsResult, $paymentResult]);
    }

    /**
     * Test production readiness
     */
    private function testProductionReadiness()
    {
        $this->info('🚀 Checking Production Readiness...');
        $this->newLine();

        $readiness = GatewayTestService::checkProductionReadiness();
        
        foreach ($readiness['checks'] as $name => $check) {
            $this->info("🔍 Testing {$name}...");
            $this->displayResult(ucfirst($name), $check);
        }

        $this->newLine();
        $this->info('📊 Production Readiness Summary:');
        $this->displayProductionSummary($readiness);
    }

    /**
     * Display test result
     */
    private function displayResult(string $name, array $result)
    {
        $status = $result['status'] ?? 'unknown';
        $message = $result['message'] ?? 'No message';
        $details = $result['details'] ?? 'No details';

        switch ($status) {
            case 'success':
                $this->line("✅ <fg=green>{$name}: {$message}</>");
                break;
            case 'warning':
                $this->line("⚠️  <fg=yellow>{$name}: {$message}</>");
                break;
            case 'error':
                $this->line("❌ <fg=red>{$name}: {$message}</>");
                break;
            default:
                $this->line("❓ <fg=white>{$name}: {$message}</>");
        }

        if ($details) {
            $this->line("   <fg=gray>{$details}</>");
        }

        // Display additional info if available
        if (isset($result['test_message'])) {
            $this->line("   <fg=blue>Test Message: {$result['test_message']}</>");
        }

        if (isset($result['base_url'])) {
            $this->line("   <fg=blue>Base URL: {$result['base_url']}</>");
        }

        $this->newLine();
    }

    /**
     * Display summary
     */
    private function displaySummary(array $results)
    {
        $passed = 0;
        $failed = 0;
        $warnings = 0;

        foreach ($results as $result) {
            $status = $result['status'] ?? 'unknown';
            switch ($status) {
                case 'success':
                    $passed++;
                    break;
                case 'error':
                    $failed++;
                    break;
                case 'warning':
                    $warnings++;
                    break;
            }
        }

        $this->line("✅ Passed: <fg=green>{$passed}</>");
        $this->line("❌ Failed: <fg=red>{$failed}</>");
        $this->line("⚠️  Warnings: <fg=yellow>{$warnings}</>");

        if ($failed === 0) {
            $this->newLine();
            $this->info('🎉 All gateways are working correctly!');
        } else {
            $this->newLine();
            $this->error('⚠️  Some gateways have issues. Please check the configuration.');
        }
    }

    /**
     * Display production readiness summary
     */
    private function displayProductionSummary(array $readiness)
    {
        $summary = $readiness['summary'];
        $ready = $readiness['ready'];

        $this->line("📊 Total Checks: <fg=blue>{$summary['total_checks']}</>");
        $this->line("✅ Passed: <fg=green>{$summary['passed']}</>");
        $this->line("❌ Failed: <fg=red>{$summary['failed']}</>");
        $this->line("⚠️  Warnings: <fg=yellow>{$summary['warnings']}</>");

        if (!empty($summary['critical_issues'])) {
            $this->newLine();
            $this->error('🚨 Critical Issues:');
            foreach ($summary['critical_issues'] as $issue) {
                $this->line("   ❌ {$issue}");
            }
        }

        $this->newLine();
        if ($ready) {
            $this->info('🎉 Production Ready! All systems are configured correctly.');
        } else {
            $this->error('⚠️  Not Production Ready. Please fix the issues above.');
        }
    }
} 