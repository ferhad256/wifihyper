<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;

class InspectTransaction extends Command
{
    protected $signature = 'transaction:inspect {transaction_id}';
    protected $description = 'Inspect transaction details and payment information';

    public function handle()
    {
        $transactionId = $this->argument('transaction_id');
        
        $transaction = Transaction::where('transaction_id', $transactionId)->first();
        if (!$transaction) {
            $this->error("Transaction '{$transactionId}' not found.");
            return 1;
        }

        $this->info("=== Transaction Details ===");
        $this->line("ID: {$transaction->transaction_id}");
        $this->line("Status: {$transaction->status}");
        $this->line("Amount: UGX {$transaction->amount}");
        $this->line("Phone: {$transaction->phone_number}");
        $this->line("Created: {$transaction->created_at}");
        $this->line("Updated: {$transaction->updated_at}");
        
        if ($transaction->paid_at) {
            $this->line("Paid At: {$transaction->paid_at}");
        }
        
        if ($transaction->failed_at) {
            $this->line("Failed At: {$transaction->failed_at}");
        }

        $this->newLine();
        $this->info("=== Payment Details ===");
        
        if ($transaction->payment_details) {
            foreach ($transaction->payment_details as $key => $value) {
                if (is_array($value)) {
                    $this->line("{$key}: " . json_encode($value));
                } else {
                    $this->line("{$key}: {$value}");
                }
            }
        } else {
            $this->warn("No payment details found!");
        }

        $this->newLine();
        $this->info("=== Relationships ===");
        
        if ($transaction->tenant) {
            $this->line("Tenant: {$transaction->tenant->name} (ID: {$transaction->tenant->id})");
        }
        
        if ($transaction->package) {
            $this->line("Package: {$transaction->package->name} (ID: {$transaction->package->id})");
        }
        
        if ($transaction->voucher) {
            $this->line("Voucher: {$transaction->voucher->code} (ID: {$transaction->voucher->id})");
        }

        return 0;
    }
} 