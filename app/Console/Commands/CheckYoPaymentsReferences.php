<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;

class CheckYoPaymentsReferences extends Command
{
    protected $signature = 'payment:check-references';
    protected $description = 'Check which transactions have Yo Payments references';

    public function handle()
    {
        $this->info('Checking Yo Payments references for all transactions...');
        $this->newLine();

        $transactions = Transaction::orderBy('created_at', 'desc')->limit(20)->get();
        
        $withReferences = 0;
        $withoutReferences = 0;
        $completedWithoutRef = 0;

        foreach ($transactions as $transaction) {
            $hasReference = !empty($transaction->payment_details['yo_payments_reference'] ?? null);
            $reference = $transaction->payment_details['yo_payments_reference'] ?? 'NO REF';
            
            if ($hasReference) {
                $withReferences++;
                $this->line("✅ {$transaction->transaction_id} - {$reference} - {$transaction->status}");
            } else {
                $withoutReferences++;
                if ($transaction->status === 'completed') {
                    $completedWithoutRef++;
                    $this->warn("⚠️  {$transaction->transaction_id} - NO REF - {$transaction->status} (COMPLETED WITHOUT REF!)");
                } else {
                    $this->line("❌ {$transaction->transaction_id} - NO REF - {$transaction->status}");
                }
            }
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("Total checked: " . $transactions->count());
        $this->line("With references: {$withReferences}");
        $this->line("Without references: {$withoutReferences}");
        $this->line("Completed without ref: {$completedWithoutRef}");

        if ($completedWithoutRef > 0) {
            $this->newLine();
            $this->warn("⚠️  WARNING: {$completedWithoutRef} transactions are marked as completed but have no Yo Payments reference!");
            $this->line("   These transactions were likely completed in development/simulation mode.");
            $this->line("   They cannot be verified with Yo Payments and may cause verification errors.");
        }

        return 0;
    }
} 