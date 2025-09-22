<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send notification email
     */
    public function sendNotificationEmail(Tenant $tenant, Notification $notification)
    {
        try {
            // Check if email notifications are enabled for this tenant
            if (!($tenant->settings['email_notifications'] ?? false)) {
                return false;
            }

            $data = [
                'tenant' => $tenant,
                'notification' => $notification,
                'subject' => $notification->title,
                'message' => $notification->message,
            ];

            Mail::send('emails.notification', $data, function ($message) use ($tenant, $notification) {
                $message->to($tenant->email, $tenant->name)
                        ->subject($notification->title);
            });

            Log::info('Notification email sent', [
                'tenant_id' => $tenant->id,
                'notification_id' => $notification->id,
                'email' => $tenant->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'tenant_id' => $tenant->id,
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send welcome email to new tenant
     */
    public function sendWelcomeEmail($email, $businessName)
    {
        try {
            $data = [
                'email' => $email,
                'business_name' => $businessName,
                'login_url' => route('login'),
            ];

            Mail::send('emails.welcome', $data, function ($message) use ($email, $businessName) {
                $message->to($email, $businessName)
                        ->subject('Welcome to WIFIHYPER');
            });

            Log::info('Welcome email sent', [
                'email' => $email,
                'business_name' => $businessName,
            ]);

            return [
                'success' => true,
                'message' => 'Welcome email sent successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send welcome email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send verification email with code
     */
    public function sendVerificationEmail($email, $businessName, $verificationCode)
    {
        try {
            $data = [
                'email' => $email,
                'business_name' => $businessName,
                'verification_code' => $verificationCode,
                'expires_in' => 5, // minutes
            ];

            Mail::send('emails.verify-email', $data, function ($message) use ($email, $businessName) {
                $message->to($email, $businessName)
                        ->subject('Verify Your Email - WIFIHYPER');
            });

            Log::info('Verification email sent', [
                'email' => $email,
                'business_name' => $businessName,
                'verification_code' => $verificationCode,
            ]);

            return [
                'success' => true,
                'message' => 'Verification email sent successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send low voucher alert email
     */
    public function sendLowVoucherAlert(Tenant $tenant, $packageName, $voucherCount)
    {
        try {
            // Check if email notifications are enabled
            if (!($tenant->settings['email_notifications'] ?? false)) {
                return false;
            }

            $data = [
                'tenant' => $tenant,
                'package_name' => $packageName,
                'voucher_count' => $voucherCount,
                'dashboard_url' => route('dashboard'),
            ];

            Mail::send('emails.low-voucher-alert', $data, function ($message) use ($tenant, $packageName) {
                $message->to($tenant->email, $tenant->name)
                        ->subject("Low Voucher Alert - {$packageName}");
            });

            Log::info('Low voucher alert email sent', [
                'tenant_id' => $tenant->id,
                'package_name' => $packageName,
                'voucher_count' => $voucherCount,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send low voucher alert email', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send transaction summary email
     */
    public function sendTransactionSummary(Tenant $tenant, $transactions, $period = 'daily')
    {
        try {
            // Check if email notifications are enabled
            if (!($tenant->settings['email_notifications'] ?? false)) {
                return false;
            }

            $data = [
                'tenant' => $tenant,
                'transactions' => $transactions,
                'period' => $period,
                'total_amount' => $transactions->sum('amount'),
                'total_transactions' => $transactions->count(),
                'dashboard_url' => route('dashboard'),
            ];

            Mail::send('emails.transaction-summary', $data, function ($message) use ($tenant, $period) {
                $message->to($tenant->email, $tenant->name)
                        ->subject(ucfirst($period) . ' Transaction Summary');
            });

            Log::info('Transaction summary email sent', [
                'tenant_id' => $tenant->id,
                'period' => $period,
                'transaction_count' => $transactions->count(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send transaction summary email', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(Tenant $tenant, $resetUrl)
    {
        try {
            $data = [
                'tenant' => $tenant,
                'reset_url' => $resetUrl,
                'expires_in' => 60, // minutes
            ];

            Mail::send('emails.password-reset', $data, function ($message) use ($tenant) {
                $message->to($tenant->email, $tenant->name)
                        ->subject('Password Reset Request - WIFIHYPER');
            });

            Log::info('Password reset email sent', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send withdrawal approval email
     */
    public function sendWithdrawalApprovalEmail($tenant, $withdrawal)
    {
        try {
            $data = [
                'tenant' => $tenant,
                'withdrawal' => $withdrawal,
                'amount' => $withdrawal->amount,
                'balance' => $tenant->wallet_balance,
                'dashboard_url' => route('dashboard'),
            ];

            Mail::send('emails.withdrawal-approval', $data, function ($message) use ($tenant) {
                $message->to($tenant->email, $tenant->name)
                        ->subject('Withdrawal Approved - WIFIHYPER');
            });

            Log::info('Withdrawal approval email sent', [
                'tenant_id' => $tenant->id,
                'withdrawal_id' => $withdrawal->id,
                'amount' => $withdrawal->amount,
                'email' => $tenant->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send withdrawal approval email', [
                'tenant_id' => $tenant->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration(Tenant $tenant)
    {
        try {
            $data = [
                'tenant' => $tenant,
                'test_time' => now()->format('Y-m-d H:i:s'),
            ];

            Mail::send('emails.test', $data, function ($message) use ($tenant) {
                $message->to($tenant->email, $tenant->name)
                        ->subject('Email Configuration Test');
            });

            Log::info('Test email sent successfully', [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send test email', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
} 