<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\YoPaymentsService;

class CheckSimulationMode extends Command
{
    protected $signature = 'payment:check-simulation {transaction_id}';
    protected $description = 'Check simulation mode and debug verification';

    public function handle()
    {
        $transactionId = $this->argument('transaction_id');
        
        $transaction = Transaction::where('transaction_id', $transactionId)->first();
        if (!$transaction) {
            $this->error("Transaction '{$transactionId}' not found.");
            return 1;
        }

        $this->info("=== Transaction: {$transaction->transaction_id} ===");
        
        if ($transaction->payment_details) {
            $this->line("Payment Details:");
            foreach ($transaction->payment_details as $key => $value) {
                if (is_array($value)) {
                    $this->line("  {$key}: " . json_encode($value));
                } else {
                    $this->line("  {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value));
                }
            }
            
            $simulationMode = $transaction->payment_details['simulation_mode'] ?? null;
            $this->newLine();
            $this->line("Simulation Mode Value: " . var_export($simulationMode, true));
            $this->line("Type: " . gettype($simulationMode));
            $this->line("Boolean Check: " . ($simulationMode ? 'true' : 'false'));
        } else {
            $this->warn("No payment details found!");
        }

        $this->newLine();
        $this->info("=== Testing Verification ===");
        
        $yoPayments = new YoPaymentsService();
        
        try {
            $result = $yoPayments->comprehensiveTransactionVerification($transaction);
            $this->line("Verification Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));
            $this->line("Message: " . ($result['message'] ?? 'No message'));
            
            if (isset($result['verification_method'])) {
                $this->line("Method Used: " . $result['verification_method']);
            }
            
            if (isset($result['verification_methods_tried'])) {
                $this->line("Methods Tried: " . implode(', ', $result['verification_methods_tried']));
            }
        } catch (\Exception $e) {
            $this->error("Verification Error: " . $e->getMessage());
        }

        return 0;
    }
} 