<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Show forgot password form
     */
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle forgot password request
     */
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = Tenant::where('email', $request->email)->first();

        // One response for every outcome: unregistered, deactivated, or a
        // link genuinely sent. A distinct "account is deactivated" message
        // here told an anonymous caller that the address WAS registered,
        // which defeated the generic message below it.
        $genericResponse = fn () => back()->with(
            'success',
            'If your email is registered, you will receive a password reset link shortly.'
        );

        if (! $tenant || ! $tenant->is_active) {
            if ($tenant) {
                \Log::info('Password reset requested for a deactivated account', [
                    'tenant_id' => $tenant->id,
                    'ip' => $request->ip(),
                ]);
            }

            return $genericResponse();
        }

        // Generate reset token
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(60); // Token expires in 1 hour

        // Store reset token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $tenant->email],
            [
                'email' => $tenant->email,
                'token' => Hash::make($token),
                'created_at' => now(),
                'expires_at' => $expiresAt,
            ]
        );

        // Send reset email
        try {
            $resetUrl = route('password.reset', ['token' => $token, 'email' => $tenant->email]);
            
            $this->emailService->sendPasswordResetEmail($tenant, $resetUrl);
            
            Log::info('Password reset email sent', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'ip' => $request->ip(),
            ]);

            return back()->with('success', 'If your email is registered, you will receive a password reset link shortly.');
            
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to send reset email. Please try again later.');
        }
    }

    /**
     * Show reset password form
     */
    public function showResetForm(Request $request, $token)
    {
        $email = $request->query('email');
        
        // Verify token exists and is valid
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetRecord) {
            return redirect()->route('password.request')
                ->with('error', 'Invalid or expired reset link. Please request a new one.');
        }

        // Verify token matches
        if (!Hash::check($token, $resetRecord->token)) {
            return redirect()->route('password.request')
                ->with('error', 'Invalid reset link. Please request a new one.');
        }

        return view('auth.reset-password', compact('token', 'email'));
    }

    /**
     * Handle password reset
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Verify token exists and is valid
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetRecord) {
            return back()->with('error', 'Invalid or expired reset link. Please request a new one.');
        }

        // Verify token matches
        if (!Hash::check($request->token, $resetRecord->token)) {
            return back()->with('error', 'Invalid reset link. Please request a new one.');
        }

        // Find tenant
        $tenant = Tenant::where('email', $request->email)->first();
        if (!$tenant) {
            return back()->with('error', 'Account not found.');
        }

        // Update password
        $tenant->password = $request->password;
        $tenant->password_changed_at = now();
        $tenant->save();

        // Delete reset token
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        Log::info('Password reset successful', [
            'tenant_id' => $tenant->id,
            'email' => $tenant->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('login')
            ->with('success', 'Password reset successful! You can now login with your new password.');
    }
}
