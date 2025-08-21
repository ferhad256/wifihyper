<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\Hotspot;
use App\Models\Package;
use App\Models\Notification;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Update profile information
     */
    public function updateProfile(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Sanitize input data
            $sanitizedData = SecurityService::sanitizeInput($request->only([
                'name', 'email', 'phone', 'business_name', 'address'
            ]));

            $tenant->update($sanitizedData);

            // Log the profile update
            Log::info('Profile updated successfully', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'updated_fields' => array_keys($sanitizedData),
                'ip_address' => $request->ip(),
            ]);

            return back()->with('success', 'Profile updated successfully!');
        } catch (\Exception $e) {
            Log::error('Profile update failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to update profile: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Validate current password
        if (!Hash::check($request->current_password, $tenant->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }

        // Validate new password strength
        $passwordErrors = SecurityService::validatePasswordStrength($request->new_password);
        if (!empty($passwordErrors)) {
            return back()->withErrors(['new_password' => $passwordErrors[0]])->withInput();
        }

        // Check if new password is same as current
        if (Hash::check($request->new_password, $tenant->password)) {
            return back()->withErrors(['new_password' => 'New password must be different from current password.'])->withInput();
        }

        try {
            // Update password in database
            $tenant->update([
                'password' => Hash::make($request->new_password),
                'password_changed_at' => now(),
            ]);

            // Log the password change
            Log::info('Password changed successfully', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Regenerate session for security
            session()->regenerate();

            return back()->with('success', 'Password changed successfully! Please log in again with your new password.');
        } catch (\Exception $e) {
            Log::error('Password change failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to change password: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Delete account with confirmation
     */
    public function deleteAccount(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        // Validate confirmation
        $validator = Validator::make($request->all(), [
            'confirmation_text' => 'required|in:DELETE MY ACCOUNT',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Verify password
        if (!Hash::check($request->password_confirmation, $tenant->password)) {
            return back()->withErrors(['password_confirmation' => 'Password is incorrect.'])->withInput();
        }

        try {
            DB::beginTransaction();

            // Log the account deletion attempt
            Log::warning('Account deletion initiated', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'business_name' => $tenant->business_name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Delete all related data
            $this->deleteTenantData($tenant);

            // Delete the tenant
            $tenant->delete();

            DB::commit();

            // Clear session and redirect to login
            session()->flush();
            
            Log::info('Account deleted successfully', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'business_name' => $tenant->business_name,
            ]);

            return redirect()->route('login')->with('success', 'Your account has been permanently deleted. All data has been removed from our system.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Account deletion failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to delete account: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Delete all tenant-related data
     */
    private function deleteTenantData(Tenant $tenant)
    {
        try {
            // Delete notifications
            Notification::where('tenant_id', $tenant->id)->delete();

            // Delete transactions
            Transaction::where('tenant_id', $tenant->id)->delete();

            // Delete vouchers
            Voucher::where('tenant_id', $tenant->id)->delete();

            // Delete packages
            $packageIds = Package::where('hotspot_id', function($query) use ($tenant) {
                $query->select('id')->from('hotspots')->where('tenant_id', $tenant->id);
            })->pluck('id');
            
            Package::whereIn('id', $packageIds)->delete();

            // Delete hotspots
            Hotspot::where('tenant_id', $tenant->id)->delete();

            // Delete subscription plans
            if (method_exists($tenant, 'subscriptionPlans')) {
                $tenant->subscriptionPlans()->delete();
            }

            // Delete any other related models
            // Add more models here as needed

            Log::info('Tenant data deleted successfully', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete tenant data', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Show account deletion confirmation page
     */
    public function showDeleteConfirmation()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        // Get account statistics for confirmation
        $stats = [
            'hotspots' => $tenant->hotspots()->count(),
            'vouchers' => $tenant->vouchers()->count(),
            'transactions' => $tenant->transactions()->count(),
            'packages' => Package::where('hotspot_id', function($query) use ($tenant) {
                $query->select('id')->from('hotspots')->where('tenant_id', $tenant->id);
            })->count(),
        ];

        return view('dashboard.delete-account', compact('tenant', 'stats'));
    }
} 