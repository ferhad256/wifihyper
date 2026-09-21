<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Notification;
use App\Models\Voucher;
use App\Models\Package;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Check for low voucher notifications and create them if needed
     */
    public function checkLowVoucherNotifications(Tenant $tenant)
    {
        try {
            // Get all packages with low voucher count (less than 5)
            $lowVoucherPackages = $tenant->hotspots()
                ->with(['packages' => function ($query) {
                    $query->whereHas('vouchers', function ($voucherQuery) {
                        $voucherQuery->where('status', 'unused');
                    }, '<', 5);
                }])
                ->get()
                ->flatMap(function ($hotspot) {
                    return $hotspot->packages->filter(function ($package) {
                        return $package->vouchers()->where('status', 'unused')->count() < 5;
                    });
                });

            foreach ($lowVoucherPackages as $package) {
                $unusedCount = $package->vouchers()->where('status', 'unused')->count();
                
                if ($unusedCount < 5) {
                    $this->createLowVoucherNotification($tenant, $package, $unusedCount);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error checking low voucher notifications', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create low voucher notification (only if not already exists for today)
     */
    protected function createLowVoucherNotification(Tenant $tenant, Package $package, int $unusedCount)
    {
        // Check if we already sent a notification for this package today
        $existingNotification = $tenant->alerts()
            ->where('type', 'low_vouchers')
            ->where('data->package_id', $package->id)
            ->whereDate('created_at', today())
            ->first();

        if ($existingNotification) {
            return; // Already notified today
        }

        // Create the notification
        $notification = $tenant->alerts()->create([
            'type' => 'low_vouchers',
            'title' => 'Low Voucher Stock Alert',
            'message' => "Package '{$package->name}' has only {$unusedCount} vouchers remaining. Please upload more vouchers to avoid service interruption.",
            'data' => [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'hotspot_id' => $package->hotspot_id,
                'hotspot_name' => $package->hotspot->name,
                'unused_count' => $unusedCount,
                'threshold' => 5,
            ],
            'status' => 'unread',
        ]);

        Log::info('Low voucher notification created', [
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'unused_count' => $unusedCount,
            'notification_id' => $notification->id,
        ]);
    }

    /**
     * Create no voucher notification (only on login, once per day)
     */
    public function checkNoVoucherNotifications(Tenant $tenant)
    {
        try {
            // Check if we already sent a no voucher notification today
            $existingNotification = $tenant->alerts()
                ->where('type', 'no_vouchers')
                ->whereDate('created_at', today())
                ->first();

            if ($existingNotification) {
                return; // Already notified today
            }

            // Check if tenant has any unused vouchers across all packages
            $totalUnusedVouchers = $tenant->vouchers()
                ->where('status', 'unused')
                ->count();

            if ($totalUnusedVouchers === 0) {
                $this->createNoVoucherNotification($tenant);
            }

        } catch (\Exception $e) {
            Log::error('Error checking no voucher notifications', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create no voucher notification
     */
    protected function createNoVoucherNotification(Tenant $tenant)
    {
        $notification = $tenant->alerts()->create([
            'type' => 'no_vouchers',
            'title' => 'No Vouchers Available',
            'message' => 'You have no unused vouchers available. Please upload vouchers to continue providing WiFi services.',
            'data' => [
                'total_unused' => 0,
                'action_required' => 'upload_vouchers',
            ],
            'status' => 'unread',
        ]);

        Log::info('No voucher notification created', [
            'tenant_id' => $tenant->id,
            'notification_id' => $notification->id,
        ]);
    }

    /**
     * Get notifications for dropdown display
     */
    public function getNotificationsForDropdown(Tenant $tenant, int $limit = 10)
    {
        return $tenant->alerts()
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'status' => $notification->status,
                    'created_at' => $notification->created_at->diffForHumans(),
                    'data' => $notification->data,
                ];
            });
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Tenant $tenant, int $notificationId)
    {
        $notification = $tenant->alerts()
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Tenant $tenant)
    {
        return $tenant->alerts()
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount(Tenant $tenant)
    {
        return $tenant->alerts()
            ->where('status', 'unread')
            ->count();
    }

    /**
     * Create a custom notification
     */
    public function createNotification(Tenant $tenant, string $type, string $title, string $message, array $data = [])
    {
        return $tenant->alerts()->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'status' => 'unread',
        ]);
    }

    /**
     * Clean up old notifications (older than 30 days)
     */
    public function cleanupOldNotifications()
    {
        $deletedCount = Notification::where('created_at', '<', now()->subDays(30))->delete();
        
        Log::info('Cleaned up old notifications', [
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }
} 