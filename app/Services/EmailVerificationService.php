<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class EmailVerificationService
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Generate and send verification code
     */
    public function sendVerificationCode(Tenant $tenant): array
    {
        try {
            // random_int, not rand: rand() is a predictable PRNG and this
            // code is a credential.
            $verificationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Stored hashed. The cache is a shared store (and in this app the
            // database), so a readable copy of a live credential does not
            // belong in it.
            $cacheKey = "email_verification_{$tenant->id}";
            Cache::put($cacheKey, [
                'code' => Hash::make($verificationCode),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)
            ], 300); // 5 minutes

            // Send verification email
            $emailResult = $this->emailService->sendVerificationEmail(
                $tenant->email,
                $tenant->business_name,
                $verificationCode
            );

            if ($emailResult['success']) {
                // The code itself is deliberately not logged.
                Log::info('Verification code sent successfully', [
                    'tenant_id' => $tenant->id,
                    'email' => $tenant->email,
                ]);

                return [
                    'success' => true,
                    'message' => 'Verification code sent to your email',
                    'expires_in' => 5
                ];
            } else {
                Log::error('Failed to send verification email', [
                    'tenant_id' => $tenant->id,
                    'email' => $tenant->email,
                    'error' => $emailResult['message']
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send verification email: ' . $emailResult['message']
                ];
            }

        } catch (\Exception $e) {
            Log::error('Email verification service error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Verification service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify the verification code
     */
    public function verifyCode(Tenant $tenant, string $code): array
    {
        try {
            $cacheKey = "email_verification_{$tenant->id}";
            $verificationData = Cache::get($cacheKey);

            if (!$verificationData) {
                return [
                    'success' => false,
                    'message' => 'Verification code has expired. Please request a new one.',
                    'code' => 'EXPIRED'
                ];
            }

            // Check if code matches
            if (! Hash::check($code, $verificationData['code'])) {
                // Increment attempts
                $verificationData['attempts']++;

                // Re-put for whatever is LEFT of the original window. Writing
                // a fresh 900s TTL here silently extended a 5 minute code to
                // 15 minutes every time someone guessed wrong.
                $remaining = max(1, (int) now()->diffInSeconds($verificationData['expires_at'], false));
                Cache::put($cacheKey, $verificationData, $remaining);

                if ($verificationData['attempts'] >= 3) {
                    // Too many attempts, expire the code
                    Cache::forget($cacheKey);
                    
                    Log::warning('Too many verification attempts', [
                        'tenant_id' => $tenant->id,
                        'email' => $tenant->email,
                        'attempts' => $verificationData['attempts']
                    ]);

                    return [
                        'success' => false,
                        'message' => 'Too many failed attempts. Please request a new verification code.',
                        'code' => 'TOO_MANY_ATTEMPTS'
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Invalid verification code. Please try again.',
                    'code' => 'INVALID_CODE',
                    'attempts_remaining' => 3 - $verificationData['attempts']
                ];
            }

            // Code is valid, mark email as verified
            $tenant->markEmailAsVerified();
            event(new Verified($tenant));

            // Clear verification cache
            Cache::forget($cacheKey);

            // Send welcome email
            try {
                $welcomeResult = $this->emailService->sendWelcomeEmail(
                    $tenant->email,
                    $tenant->business_name
                );

                if ($welcomeResult['success']) {
                    Log::info('Email verified and welcome email sent', [
                        'tenant_id' => $tenant->id,
                        'email' => $tenant->email
                    ]);
                } else {
                    Log::warning('Email verified but welcome email failed', [
                        'tenant_id' => $tenant->id,
                        'email' => $tenant->email,
                        'error' => $welcomeResult['message']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception while sending welcome email', [
                    'tenant_id' => $tenant->id,
                    'email' => $tenant->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return [
                'success' => true,
                'message' => 'Email verified successfully! Welcome email sent. You can now login to your account.',
                'tenant' => $tenant
            ];

        } catch (\Exception $e) {
            Log::error('Email verification error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Verification error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(Tenant $tenant): array
    {
        try {
            // Check if tenant is already verified
            if ($tenant->email_verified_at) {
                return [
                    'success' => false,
                    'message' => 'Email is already verified'
                ];
            }

            // Clear any existing verification data
            $cacheKey = "email_verification_{$tenant->id}";
            Cache::forget($cacheKey);

            // Send new verification code
            return $this->sendVerificationCode($tenant);

        } catch (\Exception $e) {
            Log::error('Resend verification code error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to resend verification code: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if tenant can request verification
     */
    public function canRequestVerification(Tenant $tenant): bool
    {
        if ($tenant->email_verified_at) {
            return false;
        }

        $cacheKey = "email_verification_{$tenant->id}";
        $verificationData = Cache::get($cacheKey);

        if (!$verificationData) {
            return true;
        }

        // Allow resend after 1 minute
        return now()->diffInMinutes($verificationData['expires_at']) > 4;
    }

    /**
     * Get remaining time for verification code
     */
    public function getRemainingTime(Tenant $tenant): ?int
    {
        $cacheKey = "email_verification_{$tenant->id}";
        $verificationData = Cache::get($cacheKey);

        if (!$verificationData) {
            return null;
        }

        return now()->diffInSeconds($verificationData['expires_at']);
    }
} 