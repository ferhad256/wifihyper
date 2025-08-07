# Payment Status Checking System

## Overview

The WIFIHYPER system now includes comprehensive payment status checking using the Yo Payments API to ensure accurate billing and transaction records.

## Key Components

### 1. Enhanced PaymentController Methods

#### `checkStatus($transactionId)`
- **Purpose**: Check payment status using Yo Payments API
- **Updates**: Transaction status, billing details, timestamps
- **Returns**: JSON response with status and billing information

#### `callback(Request $request)`
- **Purpose**: Handle Yo Payments callbacks
- **Updates**: Transaction status, payment details, billing information
- **Triggers**: Wallet balance update and SMS sending for completed payments

### 2. YoPaymentsService Enhancements

#### `verifyPayment($transactionId)`
- **Purpose**: Check transaction status via Yo Payments API
- **Returns**: Detailed status information including billing details

#### `checkTransactionByReference($externalReference)`
- **Purpose**: Check status using transaction reference
- **Returns**: Complete transaction details and billing information

#### `processCallback($data)`
- **Purpose**: Process Yo Payments callback data
- **Updates**: Transaction records with callback information

### 3. CheckPendingPayments Command

#### Usage
```bash
# Check up to 50 pending transactions (default)
php artisan payments:check-pending

# Check specific number of transactions
php artisan payments:check-pending --limit=100
```

#### Features
- Checks pending transactions older than 5 minutes
- Updates transaction status and billing details
- Triggers wallet balance updates and SMS sending
- Comprehensive logging and error handling
- Rate limiting to avoid API overload

## Billing Information Captured

### Transaction Records
```json
{
  "status": "completed|pending|failed",
  "paid_at": "2025-08-07 16:30:00",
  "failed_at": "2025-08-07 16:30:00",
  "payment_details": {
    "last_status_check": "2025-08-07 16:30:00",
    "yo_payments_status": "SUCCEEDED",
    "yo_payments_amount": "500",
    "yo_payments_currency": "UGX",
    "yo_payments_receipt": "REC123456789",
    "yo_payments_initiation_date": "2025-08-07 16:25:00",
    "yo_payments_completion_date": "2025-08-07 16:30:00",
    "callback_received_at": "2025-08-07 16:30:00",
    "callback_status": "SUCCEEDED",
    "callback_amount": "500",
    "callback_currency": "UGX"
  }
}
```

### Billing Information
- **Amount**: Original transaction amount
- **Transaction Fee**: Processing fee charged
- **Net Amount**: Amount after fees
- **Currency**: Transaction currency (UGX)
- **Receipt Number**: Yo Payments receipt number
- **Initiation Date**: When payment was initiated
- **Completion Date**: When payment was completed

## Status Mapping

### Yo Payments → WIFIHYPER
```php
'SUCCEEDED' => 'completed',
'SUCCESS' => 'completed',
'SUCCESSFUL' => 'completed',
'COMPLETED' => 'completed',
'PENDING' => 'pending',
'FAILED' => 'failed',
'INDETERMINATE' => 'pending',
'CANCELLED' => 'failed',
'TIMEOUT' => 'failed',
'REJECTED' => 'failed',
'DECLINED' => 'failed'
```

## Production Setup

### 1. Cron Job Configuration

Add to your server's crontab:
```bash
# Check pending payments every 5 minutes
*/5 * * * * cd /path/to/wifihyper && php artisan payments:check-pending --limit=50 >> /var/log/wifihyper/payment-checks.log 2>&1

# Check pending payments every 10 minutes (alternative)
*/10 * * * * cd /path/to/wifihyper && php artisan payments:check-pending --limit=100 >> /var/log/wifihyper/payment-checks.log 2>&1
```

### 2. Log Configuration

Ensure proper logging in `config/logging.php`:
```php
'channels' => [
    'payment_checks' => [
        'driver' => 'daily',
        'path' => storage_path('logs/payment-checks.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

### 3. Environment Variables

Ensure these are set in your `.env`:
```env
YO_PAYMENTS_BASE_URL=https://paymentsapi1.yo.co.ug/ybs/task.php
YO_PAYMENTS_FALLBACK_URL=https://paymentsapi2.yo.co.ug/ybs/task.php
YO_PAYMENTS_USERNAME=your_username
YO_PAYMENTS_PASSWORD=your_password
YO_PAYMENTS_PRIVATE_KEY_PATH=/path/to/private/key.pem
YO_PAYMENTS_PUBLIC_KEY_ENABLED=true
```

## API Endpoints

### Check Payment Status
```
GET /api/payment/status/{transactionId}
```

**Response:**
```json
{
  "success": true,
  "status": "completed",
  "is_pending": false,
  "billing_info": {
    "amount": 500,
    "transaction_fee": 50,
    "net_amount": 450,
    "currency": "UGX",
    "receipt_number": "REC123456789",
    "initiation_date": "2025-08-07 16:25:00",
    "completion_date": "2025-08-07 16:30:00"
  },
  "message": "Payment status retrieved successfully"
}
```

### Yo Payments Callback
```
POST /payment/callback
```

**Handles:**
- Payment completion notifications
- Status updates
- Billing information updates
- Automatic voucher SMS sending

## Monitoring and Alerts

### 1. Log Monitoring
Monitor these log files:
- `storage/logs/laravel.log` - General application logs
- `storage/logs/payment-checks.log` - Payment status check logs

### 2. Key Metrics to Monitor
- Number of pending transactions
- Payment completion rate
- Failed payment rate
- SMS delivery success rate
- API response times

### 3. Alert Conditions
- High number of failed payments
- Payment status check errors
- SMS delivery failures
- API connectivity issues

## Troubleshooting

### Common Issues

#### 1. Transaction Not Found
**Cause**: Transaction doesn't exist in Yo Payments system
**Solution**: Verify transaction was properly initiated

#### 2. API Connection Errors
**Cause**: Network issues or invalid credentials
**Solution**: Check API credentials and network connectivity

#### 3. Status Not Updating
**Cause**: Callback URL not accessible
**Solution**: Ensure callback URL is publicly accessible

### Debug Commands

```bash
# Test payment status checking
php artisan payments:check-pending --limit=5

# Check specific transaction
php artisan tinker
>>> $yoPayments = new \App\Services\YoPaymentsService();
>>> $result = $yoPayments->verifyPayment('TXN_1234567890_1234');
>>> dd($result);

# View recent payment logs
tail -f storage/logs/laravel.log | grep -i payment
```

## Security Considerations

### 1. API Security
- Use HTTPS for all API communications
- Implement proper authentication
- Validate all callback data

### 2. Data Protection
- Encrypt sensitive payment data
- Implement proper access controls
- Regular security audits

### 3. Rate Limiting
- Implement API rate limiting
- Add delays between status checks
- Monitor API usage

## Performance Optimization

### 1. Database Indexes
Ensure proper indexes on:
- `transactions.transaction_id`
- `transactions.status`
- `transactions.created_at`

### 2. Caching
Consider caching for:
- Payment status results
- API credentials
- Transaction details

### 3. Batch Processing
- Process multiple transactions in batches
- Implement queue system for large volumes
- Use background jobs for SMS sending

## Testing

### 1. Unit Tests
```bash
php artisan test --filter=PaymentController
```

### 2. Integration Tests
```bash
php artisan test --filter=YoPaymentsService
```

### 3. Manual Testing
```bash
# Test status checking
php artisan payments:check-pending --limit=1

# Test callback processing
curl -X POST /payment/callback -d "test_data"
```

## Summary

The enhanced payment status checking system provides:

✅ **Real-time status updates** via Yo Payments API
✅ **Comprehensive billing information** capture
✅ **Automatic wallet balance updates** for completed payments
✅ **SMS voucher delivery** upon payment completion
✅ **Robust error handling** and logging
✅ **Scheduled status checking** via cron jobs
✅ **Callback processing** for immediate updates
✅ **Detailed transaction records** for billing and reporting

This ensures accurate billing, proper transaction tracking, and reliable voucher delivery for all WiFi payments. 