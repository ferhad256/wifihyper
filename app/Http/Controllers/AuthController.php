<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Show registration form
     */
    public function showRegister()
    {
        return view('auth.register');
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
            // Create tenant without subscription plan (subscription system removed)
            $tenant = Tenant::create([
                'name' => strip_tags($request->name),
                'email' => strtolower(trim($request->email)),
                'phone' => $request->phone ? strip_tags($request->phone) : null,
                'business_name' => $request->business_name ? strip_tags($request->business_name) : null,
                'address' => $request->address ? strip_tags($request->address) : null,
                'password' => $request->password,
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
}
