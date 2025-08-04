<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old notifications (older than 30 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $notificationService = new NotificationService();
        $deletedCount = $notificationService->cleanupOldNotifications();

        $this->info("Cleaned up {$deletedCount} old notifications.");

        return Command::SUCCESS;
    }
} 