<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ResendWebhookController extends Controller
{
    /**
     * Handle Resend webhook callbacks
     */
    public function handle(Request $request)
    {
        try {
            // Log the incoming webhook
            Log::info('Resend webhook received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            // Verify webhook signature if secret is configured
            $webhookSecret = config('resend.webhook.secret');
            if ($webhookSecret) {
                $signature = $request->header('Resend-Signature');
                if (!$this->verifyWebhookSignature($request, $signature, $webhookSecret)) {
                    Log::warning('Invalid webhook signature', [
                        'signature' => $signature,
                        'expected_secret' => $webhookSecret
                    ]);
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            }

            $payload = $request->all();
            $eventType = $payload['type'] ?? 'unknown';
            $emailId = $payload['data']['id'] ?? null;
            $email = $payload['data']['to'] ?? null;

            Log::info('Processing Resend webhook', [
                'event_type' => $eventType,
                'email_id' => $emailId,
                'email' => $email
            ]);

            switch ($eventType) {
                case 'email.delivered':
                    $this->handleEmailDelivered($payload);
                    break;
                    
                case 'email.delivery_delayed':
                    $this->handleEmailDeliveryDelayed($payload);
                    break;
                    
                case 'email.bounced':
                    $this->handleEmailBounced($payload);
                    break;
                    
                case 'email.complained':
                    $this->handleEmailComplained($payload);
                    break;
                    
                case 'email.opened':
                    $this->handleEmailOpened($payload);
                    break;
                    
                case 'email.clicked':
                    $this->handleEmailClicked($payload);
                    break;
                    
                default:
                    Log::info('Unhandled webhook event type', ['type' => $eventType]);
            }

            // Store webhook data in cache for debugging
            $cacheKey = "resend_webhook_{$emailId}";
            Cache::put($cacheKey, [
                'event_type' => $eventType,
                'payload' => $payload,
                'received_at' => now(),
                'processed' => true
            ], 3600); // Store for 1 hour

            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);

        } catch (\Exception $e) {
            Log::error('Error processing Resend webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle email delivered event
     */
    protected function handleEmailDelivered($payload)
    {
        $emailId = $payload['data']['id'] ?? null;
        $email = $payload['data']['to'] ?? null;
        
        Log::info('Email delivered successfully', [
            'email_id' => $emailId,
            'email' => $email,
            'delivered_at' => $payload['data']['delivered_at'] ?? null
        ]);

        // You can add custom logic here, such as:
        // - Updating email delivery status in your database
        // - Sending notifications to users
        // - Updating analytics
    }

    /**
     * Handle email delivery delayed event
     */
    protected function handleEmailDeliveryDelayed($payload)
    {
        $emailId = $payload['data']['id'] ?? null;
        $email = $payload['data']['to'] ?? null;
        
        Log::warning('Email delivery delayed', [
            'email_id' => $emailId,
            'email' => $email,
            'reason' => $payload['data']['reason'] ?? 'unknown'
        ]);
    }

    /**
     * Handle email bounced event
     */
    protected function handleEmailBounced($payload)
    {
        $emailId = $payload['data']['id'] ?? null;
        $email = $payload['data']['to'] ?? null;
        
        Log::warning('Email bounced', [
            'email_id' => $emailId,
            'email' => $email,
            'bounce_type' => $payload['data']['bounce_type'] ?? 'unknown',
            'reason' => $payload['data']['reason'] ?? 'unknown'
        ]);

        // You can add logic to:
        // - Mark email as invalid in your database
        // - Remove user from mailing lists
        // - Update user status
    }

    /**
     * Handle email complained event
     */
    protected function handleEmailComplained($payload)
    {
        $emailId = $payload['data']['id'] ?? null;
        $email = $payload['data']['to'] ?? null;
        
        Log::warning('Email complained', [
            'email_id' => $emailId,
            'email' => $email,
            'complaint_type' => $payload['data']['complaint_type'] ?? 'unknown'
        ]);
    }

    /**
     * Handle email opened event
     */
    protected function handleEmailOpened($payload)
    {
        $emailId = $payload['data']['id'] ?? null;
        $email = $payload['data']['to'] ?? null;
        
        Log::info('Email opened', [
            'email_id' => $emailId,
            'email' => $email,
            'opened_at' => $payload['data']['opened_at'] ?? null,
            'user_agent' => $payload['data']['user_agent'] ?? null
        ]);
    }

    /**
     * Handle email clicked event
     */
    protected function handleEmailClicked($payload)
    {
        $emailId = $payload['data']['id'] ?? null;
        $email = $payload['data']['to'] ?? null;
        $url = $payload['data']['url'] ?? null;
        
        Log::info('Email link clicked', [
            'email_id' => $emailId,
            'email' => $email,
            'url' => $url,
            'clicked_at' => $payload['data']['clicked_at'] ?? null
        ]);
    }

    /**
     * Verify webhook signature
     */
    protected function verifyWebhookSignature(Request $request, $signature, $secret)
    {
        if (!$signature || !$secret) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get webhook statistics
     */
    public function stats()
    {
        $stats = [
            'total_webhooks' => 0,
            'delivered' => 0,
            'bounced' => 0,
            'complained' => 0,
            'opened' => 0,
            'clicked' => 0,
            'delayed' => 0
        ];

        // You can implement logic to get statistics from your database or cache
        // For now, return basic structure
        
        return response()->json($stats);
    }
} 