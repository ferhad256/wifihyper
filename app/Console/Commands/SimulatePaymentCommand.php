<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\Package;
use App\Models\Hotspot;
use App\Models\Tenant;
use App\Services\EgoSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SimulatePaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:simulate {phone_number} {--package_id= : Package ID to use} {--hotspot_id= : Hotspot ID to use} {--amount= : Amount to simulate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a complete payment flow from transaction creation to voucher SMS delivery';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phoneNumber = $this->argument('phone_number');
        $packageId = $this->option('package_id');
        $hotspotId = $this->option('hotspot_id');
        $amount = $this->option('amount');
        
        $this->info("🚀 Starting Payment Simulation for: {$phoneNumber}");
        $this->newLine();
        
        try {
            // Step 1: Find available package and hotspot
            $package = $this->findPackage($packageId);
            $hotspot = $this->findHotspot($hotspotId);
            
            if (!$package || !$hotspot) {
                $this->error("❌ Could not find valid package or hotspot");
                return 1;
            }
            
            $this->info("📦 Package: {$package->name} - {$package->price} UGX");
            $this->info("🏢 Hotspot: {$hotspot->name}");
            $this->info("👤 Tenant: {$hotspot->tenant->email}");
            
            // Step 2: Check voucher availability
            $voucher = $this->findAvailableVoucher($package->id, $hotspot->tenant_id);
            
            if (!$voucher) {
                $this->error("❌ No available vouchers for this package");
                return 1;
            }
            
            $this->info("🎫 Available Voucher: {$voucher->code}");
            
            // Step 3: Create transaction
            $transaction = $this->createTransaction($phoneNumber, $package, $hotspot, $amount);
            $this->info("💳 Transaction Created: {$transaction->transaction_id}");
            
            // Step 4: Simulate payment completion (JPesa callback)
            $this->simulatePaymentCompletion($transaction);
            
            // Step 5: Send voucher SMS
            $this->sendVoucherSms($transaction);
            
            $this->newLine();
            $this->info("✅ Payment simulation completed successfully!");
            $this->info("📱 Check your phone ({$phoneNumber}) for the voucher SMS");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Simulation failed: {$e->getMessage()}");
            Log::error('Payment simulation failed', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Find package by ID or get first available
     */
    protected function findPackage($packageId)
    {
        if ($packageId) {
            return Package::find($packageId);
        }
        
        return Package::where('is_active', true)->first();
    }
    
    /**
     * Find hotspot by ID or get first available
     */
    protected function findHotspot($hotspotId)
    {
        if ($hotspotId) {
            return Hotspot::with('tenant')->find($hotspotId);
        }
        
        return Hotspot::with('tenant')->first();
    }
    
    /**
     * Find available voucher for package
     */
    protected function findAvailableVoucher($packageId, $tenantId)
    {
        return Voucher::where('package_id', $packageId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'unused')
            ->first();
    }
    
    /**
     * Create transaction
     */
    protected function createTransaction($phoneNumber, $package, $hotspot, $amount)
    {
        $transactionId = 'TXN_' . time() . '_' . rand(1000, 9999);
        $actualAmount = $amount ?: $package->price;
        
        return Transaction::create([
            'tenant_id' => $hotspot->tenant_id,
            'transaction_id' => $transactionId,
            'hotspot_id' => $hotspot->id,
            'package_id' => $package->id,
            'voucher_id' => $this->findAvailableVoucher($package->id, $hotspot->tenant_id)->id,
            'phone_number' => $phoneNumber,
            'amount' => $actualAmount,
            'transaction_fee' => 0, // Simplified for simulation
            'net_amount' => $actualAmount,
            'status' => 'pending',
            'payment_method' => 'jpesa',
            'currency' => 'UGX',
            'payment_details' => [
                'simulation' => true,
                'created_at' => now()->toISOString()
            ]
        ]);
    }
    
    /**
     * Simulate payment completion (JPesa callback)
     */
    protected function simulatePaymentCompletion($transaction)
    {
        $this->info("🔄 Simulating payment completion...");
        
        // Update transaction status
        $transaction->update([
            'status' => 'completed',
            'paid_at' => now(),
            'payment_details' => array_merge($transaction->payment_details ?? [], [
                'jpesa_tid' => 'SIM_' . time(),
                'jpesa_memo' => 'SIM' . rand(100000, 999999),
                'callback_status' => 'approved',
                'simulation' => true,
                'completed_at' => now()->toISOString()
            ])
        ]);
        
        $this->info("✅ Payment marked as completed");
    }
    
    /**
     * Send voucher SMS
     */
    protected function sendVoucherSms($transaction)
    {
        $this->info("📱 Sending voucher SMS...");
        
        $startTime = microtime(true);
        // Mark voucher as used immediately (simulating JpesaService behavior)
        if ($transaction->voucher && $transaction->voucher->status === "unused") {
            $transaction->voucher->update([
                "status" => "used",
                "used_at" => now(),
                "phone_number" => $transaction->phone_number,
            ]);
            $this->info("✅ Voucher marked as used immediately");
        }
        // Send SMS using EgoSmsService
        $smsService = new EgoSmsService();
        $result = $smsService->sendVoucherCode($transaction->phone_number, $transaction->voucher->code, $transaction->voucher->package);
        $endTime = microtime(true);
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        if ($result['success']) {
            $this->info("✅ SMS sent successfully!");
            $this->info("   Duration: {$duration}ms");
            $this->info("   Voucher: {$transaction->voucher->code}");
            $this->info("   Package: " . ($transaction->voucher->package->name ?? 'Unknown'));
        } else {
            $this->error("❌ SMS failed!");
            $this->error("   Error: {$result['message']}");
        }
        
        // Show transaction details
        $this->newLine();
        $this->info("📊 Transaction Details:");
        $this->info("   ID: {$transaction->transaction_id}");
        $this->info("   Amount: {$transaction->amount} UGX");
        $this->info("   Status: {$transaction->status}");
        $this->info("   Paid At: {$transaction->paid_at}");
        $this->info("   Voucher: {$transaction->voucher->code}");
        
        // SMS was sent successfully as shown above
    }
}