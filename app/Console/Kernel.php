<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check pending payments every minute for maximum automation
        $schedule->command('payments:check-pending --limit=100')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();

        // Check pending payments every 30 seconds for critical transactions
        $schedule->command('payments:check-pending --limit=50 --critical=true')
                ->everyThirtySeconds()
                ->withoutOverlapping()
                ->runInBackground();

        // Auto-check specific transaction statuses every 15 seconds
        $schedule->command('payment:auto-check-batch --max-attempts=10 --delay=15')
                ->everyFifteenSeconds()
                ->withoutOverlapping()
                ->runInBackground();

        // Clean up old pending transactions (older than 24 hours)
        $schedule->command('payments:cleanup-pending --older-than=24')
                ->hourly()
                ->withoutOverlapping();

        // Send payment reminders for pending transactions
        $schedule->command('payments:send-reminders --older-than=30')
                ->everyFiveMinutes()
                ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 