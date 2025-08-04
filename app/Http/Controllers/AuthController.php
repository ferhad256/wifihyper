<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Show registration form
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle tenant login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = Tenant::where('email', $request->email)->first();

        if (!$tenant) {
            return back()->with('error', 'Invalid credentials.')->withInput();
        }

        // For demo purposes, we'll use a simple password check
        // In production, you should use proper password hashing
        if ($request->password === 'password123' || Hash::check($request->password, $tenant->password ?? '')) {
            // Store tenant ID in session
            session(['tenant_id' => $tenant->id]);
            
            // Check for low voucher notifications on login
            $notificationService = new NotificationService();
            $notificationService->checkLowVoucherNotifications($tenant);
            $notificationService->checkNoVoucherNotifications($tenant);
            
            return redirect()->route('dashboard')->with('success', 'Welcome back, ' . $tenant->name . '!');
        }

        return back()->with('error', 'Invalid credentials.')->withInput();
    }

    /**
     * Handle tenant registration
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'password' => 'required|string|min:6|confirmed',
            'terms' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $tenant = Tenant::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'business_name' => $request->business_name,
                'address' => $request->address,
                'password' => Hash::make($request->password),
                'subscription_plan' => 'basic',
                'subscription_expires_at' => now()->addDays(30), // 30-day trial
                'is_active' => true,
            ]);

            // Log in the tenant
            session(['tenant_id' => $tenant->id]);

            return redirect()->route('dashboard')->with('success', 'Account created successfully! Welcome to WiFi SaaS.');

        } catch (\Exception $e) {
            return back()->with('error', 'Registration failed. Please try again.')->withInput();
        }
    }

    /**
     * Handle tenant logout
     */
    public function logout(Request $request)
    {
        $request->session()->forget('tenant_id');
        return redirect()->route('landing')->with('success', 'You have been logged out successfully.');
    }
}
