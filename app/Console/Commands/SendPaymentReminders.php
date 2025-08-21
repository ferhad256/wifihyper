<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class SendPaymentReminders extends Command
{
    protected $signature = 'payments:send-reminders {--older-than=30 : Send reminders for transactions older than X minutes} {--dry-run : Show what would be sent without actually sending}';
    protected $description = 'Send payment reminders for pending transactions that have been waiting too long';

    public function handle()
    {
        $olderThan = $this->option('older-than');
        $dryRun = $this->option('dry-run');

        $this->info("📱 Starting payment reminders for transactions older than {$olderThan} minutes");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN (no SMS sent)' : 'LIVE (will send SMS)'));

        // Find pending transactions that are old enough to warrant a reminder
        $oldPendingTransactions = Transaction::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($olderThan))
            ->where(function ($query) {
                $query->whereNull('payment_details->reminder_sent_at')
                      ->orWhere('payment_details->reminder_sent_at', '<', now()->subMinutes(15));
            })
            ->get();

        if ($oldPendingTransactions->isEmpty()) {
            $this->info('✅ No pending transactions found that need reminders');
            return 0;
        }

        $this->info("📋 Found {$oldPendingTransactions->count()} transactions needing reminders");

        // Group by age for better reporting
        $groupedTransactions = $oldPendingTransactions->groupBy(function ($transaction) {
            $age = now()->diffInMinutes($transaction->created_at);
            if ($age < 60) return 'Less than 1 hour';
            if ($age < 120) return '1-2 hours';
            if ($age < 180) return '2-3 hours';
            return 'More than 3 hours';
        });

        $this->newLine();
        $this->info('=== Age Distribution ===');
        foreach ($groupedTransactions as $ageGroup => $transactions) {
            $this->info("{$ageGroup}: {$transactions->count()} transactions");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('🔍 DRY RUN MODE - No SMS messages will be sent');
            $this->info('Run without --dry-run to actually send reminders');
            
            $this->newLine();
            $this->info('=== Sample Reminders (DRY RUN) ===');
            $sampleTransactions = $oldPendingTransactions->take(3);
            foreach ($sampleTransactions as $transaction) {
                $age = now()->diffInMinutes($transaction->created_at);
                $this->line("  • {$transaction->transaction_id} - {$age}m old");
                $this->line("    To: {$transaction->phone_number}");
                $this->line("    Amount: UGX " . number_format($transaction->amount));
                $this->line("    Message: Your payment of UGX " . number_format($transaction->amount) . " is still pending. Please complete the payment to receive your voucher.");
                $this->line("");
            }
            return 0;
        }

        $this->newLine();
        $this->info('📤 Starting to send payment reminders...');

        $smsService = new SmsService();
        $sent = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($oldPendingTransactions as $transaction) {
            try {
                // Check if we've sent a reminder recently
                $lastReminder = $transaction->payment_details['reminder_sent_at'] ?? null;
                if ($lastReminder && now()->diffInMinutes($lastReminder) < 15) {
                    $this->line("  ⏭️  Skipped: {$transaction->transaction_id} (reminder sent recently)");
                    $skipped++;
                    continue;
                }

                // Prepare reminder message
                $message = $this->prepareReminderMessage($transaction);
                
                // Send SMS reminder
                $result = $smsService->sendMessage(
                    $transaction->phone_number,
                    $message,
                    'WIFIHYPER'
                );

                if ($result['success']) {
                    // Update transaction with reminder sent timestamp
                    $transaction->update([
                        'payment_details' => array_merge(
                            $transaction->payment_details ?? [],
                            [
                                'reminder_sent_at' => now(),
                                'reminder_message' => $message,
                                'reminder_count' => ($transaction->payment_details['reminder_count'] ?? 0) + 1,
                            ]
                        ),
                    ]);

                    $this->line("  ✅ Reminder sent: {$transaction->transaction_id}");
                    $sent++;

                    // Log the reminder
                    Log::info('Payment reminder sent', [
                        'transaction_id' => $transaction->transaction_id,
                        'phone_number' => $transaction->phone_number,
                        'amount' => $transaction->amount,
                        'age_minutes' => now()->diffInMinutes($transaction->created_at),
                        'reminder_count' => ($transaction->payment_details['reminder_count'] ?? 0) + 1,
                        'message' => $message,
                    ]);

                } else {
                    $this->warn("  ⚠️  Failed to send reminder: {$transaction->transaction_id}");
                    $this->warn("  Error: " . ($result['message'] ?? 'Unknown error'));
                    $errors++;

                    Log::warning('Failed to send payment reminder', [
                        'transaction_id' => $transaction->transaction_id,
                        'phone_number' => $transaction->phone_number,
                        'error' => $result['message'] ?? 'Unknown error',
                    ]);
                }

                // Small delay between SMS sends to avoid rate limiting
                usleep(500000); // 0.5 second delay

            } catch (\Exception $e) {
                $this->error("  ❌ Error sending reminder for {$transaction->transaction_id}: " . $e->getMessage());
                $errors++;

                Log::error('Error during payment reminder sending', [
                    'transaction_id' => $transaction->transaction_id,
                    'phone_number' => $transaction->phone_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Reminder Summary ===');
        $this->info("Total found: {$oldPendingTransactions->count()}");
        $this->info("Successfully sent: {$sent}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");
        $this->info("Age threshold: {$olderThan} minutes");

        // Log summary
        Log::info('Payment reminders completed', [
            'total_found' => $oldPendingTransactions->count(),
            'sent' => $sent,
            'skipped' => $skipped,
            'errors' => $errors,
            'age_threshold_minutes' => $olderThan,
            'timestamp' => now(),
        ]);

        if ($sent > 0) {
            $this->newLine();
            $this->info('💡 Reminders sent successfully!');
            $this->info('   Customers will be notified about their pending payments.');
        }

        return 0;
    }

    private function prepareReminderMessage($transaction)
    {
        $age = now()->diffInMinutes($transaction->created_at);
        $amount = number_format($transaction->amount);
        
        if ($age < 60) {
            return "Your payment of UGX {$amount} is still pending. Please complete the payment to receive your voucher. Thank you for choosing WIFIHYPER!";
        } elseif ($age < 120) {
            return "Reminder: Your payment of UGX {$amount} is still pending. Please complete the payment to receive your voucher. WIFIHYPER";
        } else {
            return "URGENT: Your payment of UGX {$amount} has been pending for over " . round($age / 60, 1) . " hours. Please complete the payment to receive your voucher. WIFIHYPER";
        }
    }
} 