<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Concerns\ResolvesTenant;

class SettingsController extends Controller
{
    use ResolvesTenant;

    /**
     * Update appearance settings
     */
    public function updateAppearance(Request $request)
    {
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'theme_mode' => 'required|in:light,dark,auto',
            'sidebar_collapsed' => 'required|in:0,1,2',
            'compact_mode' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $settings = $tenant->settings ?? [];
            $settings['theme_mode'] = $request->theme_mode;
            $settings['sidebar_collapsed'] = $request->sidebar_collapsed;
            $settings['compact_mode'] = $request->has('compact_mode');

            $tenant->update(['settings' => $settings]);

            return back()->with('success', 'Appearance settings updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update appearance settings: ' . $e->getMessage());
        }
    }

    /**
     * Update notification settings
     */
    public function updateNotifications(Request $request)
    {
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'low_stock_notifications' => 'boolean',
            'transaction_notifications' => 'boolean',
            'email_notifications' => 'boolean',
            'notification_frequency' => 'required|in:immediate,hourly,daily',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $settings = $tenant->settings ?? [];
            $settings['low_stock_notifications'] = $request->has('low_stock_notifications');
            $settings['transaction_notifications'] = $request->has('transaction_notifications');
            $settings['email_notifications'] = $request->has('email_notifications');
            $settings['notification_frequency'] = $request->notification_frequency;

            $tenant->update(['settings' => $settings]);

            return back()->with('success', 'Notification settings updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update notification settings: ' . $e->getMessage());
        }
    }

    /**
     * Update system settings
     */
    public function updateSystem(Request $request)
    {
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'boolean',
            'timezone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $settings = $tenant->settings ?? [];
            $settings['timezone'] = $request->timezone;

            $tenant->update([
                'is_active' => $request->has('is_active'),
                'settings' => $settings,
            ]);

            return back()->with('success', 'System settings updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update system settings: ' . $e->getMessage());
        }
    }

    /**
     * Update security settings
     */
    public function updateSecurity(Request $request)
    {
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'two_factor_auth' => 'boolean',
            'session_timeout' => 'boolean',
            'password_expiry_days' => 'required|in:0,30,60,90',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $settings = $tenant->settings ?? [];
            $settings['two_factor_auth'] = $request->has('two_factor_auth');
            $settings['session_timeout'] = $request->has('session_timeout');
            $settings['password_expiry_days'] = $request->password_expiry_days;

            $tenant->update(['settings' => $settings]);

            return back()->with('success', 'Security settings updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update security settings: ' . $e->getMessage());
        }
    }

    /**
     * Test email configuration
     */
    public function testEmail(Request $request)
    {
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $emailService = new EmailService();
            $result = $emailService->testEmailConfiguration($tenant);

            if ($result) {
                return response()->json(['success' => true, 'message' => 'Test email sent successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to send test email']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
} 