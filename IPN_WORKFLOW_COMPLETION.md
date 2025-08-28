# IPN Workflow Completion Guide

## Overview
This guide ensures that your IPN (Instant Payment Notification) URLs are properly listening to Yo Payments and can complete payment workflows successfully.

## 🎯 Key Requirements

### 1. **IPN URLs Must Be Accessible**
- ✅ `https://wifihyper.com/payment/callback` - Success notifications
- ✅ `https://wifihyper.com/payment/failed` - Failure notifications
- ✅ Both endpoints must accept POST requests
- ✅ Both endpoints must return HTTP 200 OK

### 2. **Payment Workflow Completion**
- ✅ Update transaction status
- ✅ Update wallet balance
- ✅ Send voucher SMS
- ✅ Mark vouchers as used
- ✅ Handle errors gracefully

## 🔧 Server Configuration

### **Ubuntu Production Server Setup**

```bash
# Navigate to your project
cd /var/www/wifihyper

# Update .env file with IPN URLs
sudo nano .env
```

Add these lines to your `.env`:
```bash
# Yo Payments IPN URLs
YO_PAYMENTS_IPN_SUCCESS_URL=https://wifihyper.com/payment/callback
YO_PAYMENTS_IPN_FAILURE_URL=https://wifihyper.com/payment/failed
YO_PAYMENTS_IPN_PENDING_URL=https://wifihyper.com/payment/callback

# IPN Configuration
YO_PAYMENTS_IPN_ENABLED=true
YO_PAYMENTS_IPN_TIMEOUT=30
YO_PAYMENTS_IPN_RETRY_ATTEMPTS=3
```

### **Clear Caches and Restart Services**

```bash
# Clear all Laravel caches
sudo php artisan config:clear
sudo php artisan cache:clear
sudo php artisan route:clear
sudo php artisan view:clear
sudo php artisan optimize:clear

# Restart web server
sudo systemctl restart apache2
# OR if using nginx:
# sudo systemctl restart nginx

# Restart PHP-FPM if applicable
sudo systemctl restart php8.1-fpm
```

## 🧪 Testing IPN Endpoints

### **1. Test Command**

Run this comprehensive test:

```bash
# Create test data and test endpoints
sudo php artisan test:ipn-endpoints --create-test-data

# Test with existing transaction
sudo php artisan test:ipn-endpoints --transaction-id=TXN_123456
```

### **2. Manual Testing**

Test endpoints manually:

```bash
# Test success callback
curl -X POST https://wifihyper.com/payment/callback \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "external_ref=TXN_TEST_123&Status=OK&Amount=500&Currency=UGX"

# Test failure notification
curl -X POST https://wifihyper.com/payment/failed \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "failed_transaction_reference=TXN_TEST_123&transaction_init_date=2025-01-27&verification=test_verification"
```

### **3. Check Response Codes**

Both endpoints should return:
```bash
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8
OK
```

## 📊 Monitoring IPN Requests

### **1. Laravel Logs**

Monitor payment-related logs:

```bash
# Real-time log monitoring
tail -f storage/logs/laravel-*.log | grep -E "(payment|callback|IPN|Yo Payments)"

# Search for specific transaction
grep "TXN_123456" storage/logs/laravel-*.log
```

### **2. Server Access Logs**

Monitor web server access:

```bash
# Apache
tail -f /var/log/apache2/access.log | grep "payment"

# Nginx
tail -f /var/log/nginx/access.log | grep "payment"
```

### **3. Database Monitoring**

Check transaction status changes:

```sql
-- Check recent transactions
SELECT transaction_id, status, paid_at, failed_at, updated_at 
FROM transactions 
ORDER BY updated_at DESC 
LIMIT 10;

-- Check payment details
SELECT transaction_id, payment_details 
FROM transactions 
WHERE payment_details->>'ipn_processed_at' IS NOT NULL;
```

## 🔍 Troubleshooting Common Issues

### **Issue 1: Endpoints Not Accessible**

**Symptoms:**
- `curl` returns connection refused
- Yo Payments gets timeout errors

**Solutions:**
```bash
# Check if web server is running
sudo systemctl status apache2
sudo systemctl status nginx

# Check firewall
sudo ufw status
sudo ufw allow 80
sudo ufw allow 443

# Check web server configuration
sudo apache2ctl -S
sudo nginx -t
```

### **Issue 2: CSRF Token Errors**

**Symptoms:**
- `419 Page Expired` errors
- CSRF token mismatch

**Solutions:**
```php
// Routes are already configured correctly:
Route::post('/payment/callback', [PaymentController::class, 'callback'])
    ->name('payment.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/payment/failed', [PaymentController::class, 'failed'])
    ->name('payment.failed.post')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

### **Issue 3: Transaction Not Found**

**Symptoms:**
- `Transaction not found` errors in logs
- IPN processing fails

**Solutions:**
```bash
# Check transaction exists
php artisan tinker
>>> \App\Models\Transaction::where('transaction_id', 'TXN_123456')->first();

# Check transaction status
>>> \App\Models\Transaction::where('status', 'pending')->get();
```

### **Issue 4: Workflow Completion Fails**

**Symptoms:**
- Payment marked as completed but no SMS sent
- Wallet balance not updated

**Solutions:**
```bash
# Check workflow error logs
grep "workflow_error" storage/logs/laravel-*.log

# Test individual workflow steps
php artisan tinker
>>> $transaction = \App\Models\Transaction::find(1);
>>> app(\App\Http\Controllers\PaymentController::class)->updateWalletBalance($transaction->transaction_id);
```

## 🚀 Production Deployment Checklist

### **Before Going Live:**

- [ ] IPN URLs configured in `.env`
- [ ] Caches cleared
- [ ] Web server restarted
- [ ] Endpoints tested with curl
- [ ] Test transaction created and processed
- [ ] Logs monitored for errors
- [ ] Database transactions verified

### **Monitoring in Production:**

- [ ] Set up log rotation
- [ ] Monitor IPN response times
- [ ] Track payment success rates
- [ ] Monitor workflow completion rates
- [ ] Set up alerts for failures

## 📱 Yo Payments Integration

### **1. Notify Yo Payments Support**

Contact Yo Payments to:
- Enable IPN for your account
- Test IPN endpoints
- Verify notification URLs
- Set up retry mechanisms

### **2. IPN Testing Mode**

Request Yo Payments to:
- Enable sandbox/testing mode
- Send test notifications
- Verify response handling
- Test retry mechanisms

## 🔒 Security Best Practices

### **1. IP Whitelisting (Optional)**

```php
// In PaymentController
protected function isFromYoPayments($request)
{
    $yoPaymentsIPs = [
        '41.210.0.0/16',  // Yo Payments IP range
        '196.200.0.0/16', // Additional IP range
    ];
    
    $clientIP = $request->ip();
    
    foreach ($yoPaymentsIPs as $ipRange) {
        if ($this->ipInRange($clientIP, $ipRange)) {
            return true;
        }
    }
    
    return false;
}
```

### **2. Signature Verification**

Already implemented for failure notifications:
- Uses public key authentication
- Verifies Yo Payments signatures
- Prevents unauthorized notifications

## 📞 Support and Debugging

### **When Things Go Wrong:**

1. **Check Laravel logs first**
2. **Verify endpoint accessibility**
3. **Test with curl commands**
4. **Check database transactions**
5. **Monitor web server logs**
6. **Contact Yo Payments support**

### **Useful Commands:**

```bash
# Test IPN endpoints
php artisan test:ipn-endpoints --create-test-data

# Check configuration
php artisan config:show services.yo_payments

# Monitor logs
tail -f storage/logs/laravel-*.log | grep payment

# Test endpoints
curl -X POST https://wifihyper.com/payment/callback -d "test=data"
```

## 🎯 Success Indicators

Your IPN system is working correctly when:

- ✅ Endpoints return HTTP 200 OK
- ✅ Transactions are updated in database
- ✅ Wallet balances are updated
- ✅ SMS notifications are sent
- ✅ Vouchers are marked as used
- ✅ Logs show successful processing
- ✅ No infinite retry loops
- ✅ Yo Payments receives successful responses

## 🔗 Related Files

- `app/Http/Controllers/PaymentController.php` - IPN handling
- `app/Services/YoPaymentsService.php` - Payment processing
- `config/services.php` - IPN configuration
- `routes/web.php` - IPN endpoint routes
- `app/Console/Commands/TestIpnEndpoints.php` - Testing command 