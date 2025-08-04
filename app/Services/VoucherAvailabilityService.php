<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Package;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class VoucherAvailabilityService
{
    /**
     * Check voucher availability for a package
     */
    public function checkVoucherAvailability(Tenant $tenant, Package $package): array
    {
        $availableVouchers = $tenant->vouchers()
            ->where('status', 'unused')
            ->where('package_id', $package->id)
            ->whereNull('used_at')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->count();

        $result = [
            'available' => $availableVouchers,
            'has_vouchers' => $availableVouchers > 0,
            'is_low' => $availableVouchers > 0 && $availableVouchers <= 5,
            'is_out' => $availableVouchers === 0,
        ];

        // Create notifications for low or out of stock vouchers
        $this->createAvailabilityNotifications($tenant, $package, $result);

        return $result;
    }

    /**
     * Check voucher availability for all packages of a tenant
     */
    public function checkAllPackagesAvailability(Tenant $tenant): array
    {
        $packages = $tenant->hotspots()
            ->with('packages')
            ->get()
            ->flatMap(function($hotspot) {
                return $hotspot->packages;
            });

        $availability = [];
        foreach ($packages as $package) {
            $availability[$package->id] = $this->checkVoucherAvailability($tenant, $package);
        }

        return $availability;
    }

    /**
     * Create notifications for voucher availability issues
     */
    private function createAvailabilityNotifications(Tenant $tenant, Package $package, array $availability): void
    {
        // Check if notification already exists for this package
        $existingNotification = $tenant->notifications()
            ->where('type', 'voucher_availability')
            ->where('data->package_id', $package->id)
            ->where('status', 'unread')
            ->first();

        if ($availability['is_out']) {
            // Create out of stock notification
            if (!$existingNotification || $existingNotification->data['status'] !== 'out') {
                Notification::create([
                    'tenant_id' => $tenant->id,
                    'type' => 'voucher_availability',
                    'title' => 'Vouchers Out of Stock',
                    'message' => "No vouchers available for package '{$package->name}' in hotspot '{$package->hotspot->name}'. Please upload more vouchers to continue selling this package.",
                    'data' => [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'hotspot_id' => $package->hotspot->id,
                        'hotspot_name' => $package->hotspot->name,
                        'status' => 'out',
                        'available_count' => 0,
                        'timestamp' => now()->toISOString(),
                        'action_required' => 'upload_vouchers',
                    ],
                ]);

                Log::warning('Vouchers out of stock notification created', [
                    'tenant_id' => $tenant->id,
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'hotspot_id' => $package->hotspot->id,
                    'hotspot_name' => $package->hotspot->name,
                    'timestamp' => now()->toISOString(),
                ]);
            }
        } elseif ($availability['is_low']) {
            // Create low stock notification
            if (!$existingNotification || $existingNotification->data['status'] !== 'low') {
                Notification::create([
                    'tenant_id' => $tenant->id,
                    'type' => 'voucher_availability',
                    'title' => 'Low Voucher Stock',
                    'message' => "Only {$availability['available']} vouchers remaining for package '{$package->name}' in hotspot '{$package->hotspot->name}'. Consider uploading more vouchers soon.",
                    'data' => [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'hotspot_id' => $package->hotspot->id,
                        'hotspot_name' => $package->hotspot->name,
                        'status' => 'low',
                        'available_count' => $availability['available'],
                        'timestamp' => now()->toISOString(),
                        'action_required' => 'consider_upload',
                    ],
                ]);

                Log::info('Low voucher stock notification created', [
                    'tenant_id' => $tenant->id,
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'hotspot_id' => $package->hotspot->id,
                    'hotspot_name' => $package->hotspot->name,
                    'available_count' => $availability['available'],
                    'timestamp' => now()->toISOString(),
                ]);
            }
        } else {
            // If vouchers are available and not low, mark existing notification as read
            if ($existingNotification) {
                $existingNotification->markAsRead();
            }
        }
    }

    /**
     * Get unread notifications for a tenant
     */
    public function getUnreadNotifications(Tenant $tenant): \Illuminate\Database\Eloquent\Collection
    {
        return $tenant->notifications()
            ->where('status', 'unread')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(int $notificationId, Tenant $tenant): bool
    {
        $notification = $tenant->notifications()
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a tenant
     */
    public function markAllNotificationsAsRead(Tenant $tenant): int
    {
        return $tenant->notifications()
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
    }
} 