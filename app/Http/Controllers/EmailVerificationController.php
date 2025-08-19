<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    protected $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Show the email verification page
     */
    public function show(Request $request)
    {
        $email = $request->query('email');
        
        if (!$email) {
            return redirect()->route('login')->with('error', 'Email address is required for verification.');
        }

        $tenant = Tenant::where('email', $email)->first();
        
        if (!$tenant) {
            return redirect()->route('login')->with('error', 'Invalid email address.');
        }

        if ($tenant->hasVerifiedEmail()) {
            return redirect()->route('login')->with('success', 'Email is already verified. You can now login.');
        }

        return view('auth.verify-email', compact('email'));
    }

    /**
     * Verify the email verification code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:tenants,email',
            'verification_code' => 'required|string|size:6|regex:/^[0-9]+$/'
        ]);

        $tenant = Tenant::where('email', $request->email)->first();
        
        if (!$tenant) {
            return back()->with('error', 'Invalid email address.');
        }

        if ($tenant->hasVerifiedEmail()) {
            return redirect()->route('login')->with('success', 'Email is already verified. You can now login.');
        }

        $result = $this->emailVerificationService->verifyCode($tenant, $request->verification_code);

        if ($result['success']) {
            Log::info('Email verified successfully', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email
            ]);

            return redirect()->route('login')->with('success', $result['message']);
        } else {
            Log::warning('Email verification failed', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'code' => $request->verification_code,
                'error' => $result['message']
            ]);

            return back()->with('error', $result['message']);
        }
    }

    /**
     * Resend verification code
     */
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:tenants,email'
        ]);

        $tenant = Tenant::where('email', $request->email)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address.'
            ], 400);
        }

        if ($tenant->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified.'
            ], 400);
        }

        if (!$this->emailVerificationService->canRequestVerification($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another code.'
            ], 429);
        }

        $result = $this->emailVerificationService->resendVerificationCode($tenant);

        if ($result['success']) {
            Log::info('Verification code resent', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email
            ]);
        } else {
            Log::error('Failed to resend verification code', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'error' => $result['message']
            ]);
        }

        return response()->json($result);
    }

    /**
     * Check verification status
     */
    public function status(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:tenants,email'
        ]);

        $tenant = Tenant::where('email', $request->email)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address.'
            ], 400);
        }

        $remainingTime = $this->emailVerificationService->getRemainingTime($tenant);
        
        return response()->json([
            'success' => true,
            'verified' => $tenant->hasVerifiedEmail(),
            'active' => $tenant->is_active,
            'remaining_time' => $remainingTime,
            'can_request' => $this->emailVerificationService->canRequestVerification($tenant)
        ]);
    }
} 