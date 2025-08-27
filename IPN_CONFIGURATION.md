# IPN (Instant Payment Notification) Configuration Guide

## Overview
This guide explains how to configure IPN URLs for Yo Payments to automatically notify your system when payment statuses change.

## What is IPN?
IPN (Instant Payment Notification) is a webhook system that allows Yo Payments to automatically send payment status updates to your server in real-time, eliminating the need for manual status checking.

## Current IPN Routes in Your System

### 1. Success/Final Status Callback
- **URL**: `https://yourdomain.com/payment/callback`
- **Method**: POST
- **Purpose**: Receives successful payment notifications
- **Route**: `Route::post('/payment/callback', [PaymentController::class, 'callback'])`

### 2. Failure Notification
- **URL**: `https://yourdomain.com/payment/failed`
- **Method**: POST
- **Purpose**: Receives payment failure notifications
- **Route**: `Route::post('/payment/failed', [PaymentController::class, 'failed'])`

### 3. Subscription Payment Callback
- **URL**: `https://yourdomain.com/subscription/payment/callback`
- **Method**: POST
- **Purpose**: Receives subscription payment notifications
- **Route**: `Route::post('/subscription/payment/callback', [PaymentController::class, 'subscriptionCallback'])`

## Environment Variables to Configure

Add these to your `.env` file:

```env
# Yo Payments IPN Configuration
YO_PAYMENTS_IPN_ENABLED=true
YO_PAYMENTS_IPN_SUCCESS_URL=https://yourdomain.com/payment/callback
YO_PAYMENTS_IPN_FAILURE_URL=https://yourdomain.com/payment/failed
YO_PAYMENTS_IPN_PENDING_URL=https://yourdomain.com/payment/callback
YO_PAYMENTS_IPN_TIMEOUT=30
YO_PAYMENTS_IPN_RETRY_ATTEMPTS=3
```

## Yo Payments Dashboard Configuration

### Step 1: Log into Yo Payments Dashboard
1. Go to your Yo Payments merchant dashboard
2. Navigate to **Settings** → **IPN Configuration** or **Webhook Settings**

### Step 2: Configure IPN URLs
Set the following URLs in your Yo Payments dashboard:

| **Event Type** | **IPN URL** | **Description** |
|----------------|--------------|-----------------|
| **Payment Success** | `https://yourdomain.com/payment/callback` | When payment is completed successfully |
| **Payment Failure** | `https://yourdomain.com/payment/failed` | When payment fails or is declined |
| **Payment Pending** | `https://yourdomain.com/payment/callback` | When payment is pending (optional) |
| **Subscription Success** | `https://yourdomain.com/subscription/payment/callback` | For subscription payments |

### Step 3: Configure IPN Settings
- **Enable IPN**: ✅ Yes
- **Timeout**: 30 seconds
- **Retry Attempts**: 3
- **SSL Required**: ✅ Yes (recommended)

## IPN Data Format

### Success Callback Data
```xml
<?xml version="1.0" encoding="UTF-8"?>
<AutoCreate>
  <Response>
    <Status>OK</Status>
    <StatusCode>0</StatusCode>
    <TransactionStatus>SUCCEEDED</TransactionStatus>
    <TransactionReference>YO_1234567890_ABCDEF</TransactionReference>
    <Amount>1000</Amount>
    <Currency>UGX</Currency>
    <IssuedReceiptNumber>RCPT123456</IssuedReceiptNumber>
    <TransactionInitiationDate>2025-08-27 20:30:00</TransactionInitiationDate>
    <TransactionCompletionDate>2025-08-27 20:31:15</TransactionCompletionDate>
  </Response>
</AutoCreate>
```

### Failure Callback Data
According to Yo Payments API 6.4: Transaction Failure Notification API

**HTTP POST Parameters:**
- `failed_transaction_reference` - Reference to the failed transaction
- `transaction_init_date` - Date/time when transaction was initiated
- `verification` - Base64 encoded RSA signature for verification

**Sample Failure Notification:**
```
failed_transaction_reference=12345678&transaction_init_date=2012-02-13+00%3A00%3A00&verification=yGtrCY0gb1EaTu6ugjG6+DQxrmxy7LAX0O9yD2EygZmjx
```

**Signature Verification:**
The verification parameter contains a base64 encoded RSA signature that must be verified using:
1. Concatenate: `failed_transaction_reference + transaction_init_date`
2. Decode base64 signature
3. Verify using SHA1 algorithm and Yo Payments public key

## Testing IPN Configuration

### 1. Test with Yo Payments Test Environment
- Use Yo Payments sandbox/test environment first
- Verify callbacks are received correctly
- Check logs for any errors

### 2. Monitor IPN Logs
Your system logs all IPN calls. Check:
```bash
tail -f storage/logs/laravel-*.log | grep "Yo Payments"
```

### 3. Test IPN Endpoints
You can test your endpoints manually:
```bash
# Test success callback
curl -X POST https://yourdomain.com/payment/callback \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "Status=OK&TransactionReference=TEST_123&Amount=1000"

# Test failure callback
curl -X POST https://yourdomain.com/payment/failed \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "Status=ERROR&TransactionReference=TEST_123&FailureReason=Test failure"
```

## Security Considerations

### 1. CSRF Protection
- IPN routes are already excluded from CSRF protection
- This is necessary for external webhook calls

### 2. IP Whitelisting (Optional)
Consider whitelisting Yo Payments IP addresses:
```php
// In your middleware or controller
$allowedIPs = [
    '41.210.0.0/16',  // Yo Payments IP range (example)
    '196.0.0.0/8',    // Yo Payments IP range (example)
];

if (!in_array(request()->ip(), $allowedIPs)) {
    abort(403, 'Unauthorized IP');
}
```

### 3. Signature Verification (If Available)
If Yo Payments provides signature verification:
```php
// Verify webhook signature
$signature = request()->header('X-Yo-Signature');
$payload = request()->getContent();
$expectedSignature = hash_hmac('sha256', $payload, config('services.yo_payments.webhook_secret'));

if (!hash_equals($signature, $expectedSignature)) {
    abort(401, 'Invalid signature');
}
```

## Troubleshooting

### Common Issues

1. **IPN Not Received**
   - Check if URLs are accessible from internet
   - Verify SSL certificates are valid
   - Check firewall settings

2. **IPN Received but Not Processed**
   - Check Laravel logs for errors
   - Verify database connections
   - Check if transaction exists in system

3. **Duplicate IPNs**
   - Implement idempotency checks
   - Use transaction reference as unique identifier

### Debug Commands
```bash
# Check IPN configuration
php artisan config:show services.yo_payments.ipn_urls

# Test IPN endpoints
php artisan route:list | grep payment

# Check recent IPN logs
tail -n 100 storage/logs/laravel-*.log | grep -i "callback\|ipn"
```

## Production Checklist

- [ ] IPN URLs configured in Yo Payments dashboard
- [ ] Environment variables set correctly
- [ ] SSL certificates valid and accessible
- [ ] Firewall allows Yo Payments IPs
- [ ] IPN endpoints tested and working
- [ ] Logging and monitoring configured
- [ ] Error handling implemented
- [ ] Backup verification process in place

## Support

If you encounter issues:
1. Check Laravel logs first
2. Verify Yo Payments dashboard configuration
3. Test endpoints manually
4. Contact Yo Payments support if needed 