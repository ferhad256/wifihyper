<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class FixMissingReferences extends Command
{
    protected $signature = 'payment:fix-references {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix transactions missing Yo Payments references by marking them as simulated';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        } else {
            $this->info('🔧 FIXING MISSING REFERENCES');
        }
        $this->newLine();

        // Find transactions that are completed but missing references
        $transactionsToFix = Transaction::where('status', 'completed')
            ->where(function($query) {
                $query->whereNull('payment_details')
                      ->orWhereRaw("JSON_EXTRACT(payment_details, '$.yo_payments_reference') IS NULL");
            })
            ->get();

        if ($transactionsToFix->isEmpty()) {
            $this->info('✅ No transactions need fixing!');
            return 0;
        }

        $this->info("Found {$transactionsToFix->count()} transactions to fix:");
        $this->newLine();

        foreach ($transactionsToFix as $transaction) {
            $this->line("📋 {$transaction->transaction_id} - UGX {$transaction->amount} - {$transaction->phone_number}");
            
            if ($dryRun) {
                $this->line("   Would mark as simulated with reference: SIM_{$transaction->transaction_id}");
            } else {
                // Create the payment details JSON
                $paymentDetails = [
                    'yo_payments_reference' => 'SIM_' . $transaction->transaction_id,
                    'simulation_mode' => true,
                    'simulated_at' => now()->toISOString(),
                    'fixed_at' => now()->toISOString(),
                    'fix_note' => 'Automatically fixed missing reference by marking as simulated',
                    'Response' => [
                        'Status' => 'OK',
                        'StatusCode' => '0',
                        'TransactionStatus' => 'SUCCEEDED',
                        'TransactionReference' => 'SIM_' . $transaction->transaction_id,
                        'StatusMessage' => 'Payment simulated successfully for development',
                    ]
                ];

                // Use raw SQL to ensure the update works
                $updated = DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'payment_details' => json_encode($paymentDetails),
                        'updated_at' => now(),
                    ]);

                if ($updated) {
                    $this->line("   ✅ Fixed: Marked as simulated with reference: SIM_{$transaction->transaction_id}");
                } else {
                    $this->error("   ❌ Failed to update transaction: {$transaction->transaction_id}");
                }
            }
        }

        $this->newLine();
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN COMPLETED');
            $this->line('Run without --dry-run to apply these fixes');
        } else {
            $this->info('✅ REFERENCES FIXED SUCCESSFULLY');
            $this->line("Fixed {$transactionsToFix->count()} transactions");
        }

        return 0;
    }
} 