<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\EmailVerificationService;
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
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = Tenant::where('email', $request->email)->first();

        if (!$tenant) {
            // Use same error message to prevent user enumeration
            return back()->with('error', 'Invalid credentials.')->withInput();
        }

        // Check if email is verified
        if (!$tenant->hasVerifiedEmail()) {
            \Log::warning('Login attempt with unverified email', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'ip' => $request->ip(),
            ]);

            // Send verification email if not already sent recently
            $emailVerificationService = new \App\Services\EmailVerificationService(new \App\Services\EmailService());
            if ($emailVerificationService->canRequestVerification($tenant)) {
                $emailVerificationService->sendVerificationCode($tenant);
            }

            return redirect()->route('verification.show', ['email' => $tenant->email])
                ->with('error', 'Please verify your email address before logging in. A new verification code has been sent.');
        }

        // Check if account is active
        if (!$tenant->is_active) {
            return back()->with('error', 'Account is deactivated. Please contact support.')->withInput();
        }

        // Verify password with proper hashing
        if (Hash::check($request->password, $tenant->password)) {
            // Regenerate session to prevent session fixation
            session()->regenerate();
            
            // Store tenant ID in session
            session(['tenant_id' => $tenant->id]);
            session(['last_activity' => time()]);
            
            // Log successful login
            \Log::info('Successful login', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            // Check for low voucher notifications on login
            $notificationService = new NotificationService();
            $notificationService->checkLowVoucherNotifications($tenant);
            $notificationService->checkNoVoucherNotifications($tenant);
            
            return redirect()->route('dashboard')->with('success', 'Welcome back, ' . $tenant->name . '!');
        }

        // Log failed login attempt
        \Log::warning('Failed login attempt', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('error', 'Invalid credentials.')->withInput();
    }

    /**
     * Handle tenant registration
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'email' => 'required|email|unique:tenants,email|max:255',
            'phone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'password' => 'required|string|min:8|max:255|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'terms' => 'required|accepted',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'name.regex' => 'Name can only contain letters and spaces.',
            'phone.regex' => 'Phone number can only contain numbers, spaces, and basic symbols.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Get the Starter Plan
            $starterPlan = \App\Models\SubscriptionPlan::where('slug', 'starter')->first();
            
            $tenant = Tenant::create([
                'name' => strip_tags($request->name),
                'email' => strtolower(trim($request->email)),
                'phone' => $request->phone ? strip_tags($request->phone) : null,
                'business_name' => $request->business_name ? strip_tags($request->business_name) : null,
                'address' => $request->address ? strip_tags($request->address) : null,
                'password' => Hash::make($request->password),
                'subscription_plan_id' => $starterPlan->id,
                'subscription_expires_at' => now()->addDays(30), // 30-day trial
                'is_active' => false, // Account inactive until email verification
                'email_verified_at' => null, // Email not verified yet
            ]);

            // Log successful registration
            \Log::info('New tenant registration', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send verification email
            $emailVerificationService = new \App\Services\EmailVerificationService(new \App\Services\EmailService());
            $verificationResult = $emailVerificationService->sendVerificationCode($tenant);

            if ($verificationResult['success']) {
                \Log::info('Verification email sent during registration', [
                    'tenant_id' => $tenant->id,
                    'email' => $tenant->email
                ]);

                // Redirect to verification page
                return redirect()->route('verification.show', ['email' => $tenant->email])
                    ->with('success', 'Account created successfully! Please check your email for the verification code to activate your account.');
            } else {
                \Log::error('Failed to send verification email during registration', [
                    'tenant_id' => $tenant->id,
                    'email' => $tenant->email,
                    'error' => $verificationResult['message']
                ]);

                // Delete the tenant if verification email fails
                $tenant->delete();

                return back()->with('error', 'Registration failed: Could not send verification email. Please try again.')->withInput();
            }

        } catch (\Exception $e) {
            \Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            
            return back()->with('error', 'Registration failed. Please try again.')->withInput();
        }
    }



    /**
     * Handle tenant logout
     */
    public function logout(Request $request)
    {
        // Log logout activity
        if (session('tenant_id')) {
            \Log::info('User logout', [
                'tenant_id' => session('tenant_id'),
                'ip' => $request->ip(),
            ]);
        }
        
        // Clear all session data
        session()->flush();
        
        // Regenerate session ID
        session()->regenerate();
        
        return redirect()->route('landing')->with('success', 'You have been logged out successfully.');
    }
}
