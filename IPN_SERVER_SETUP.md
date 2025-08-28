# IPN (Instant Payment Notification) Server Setup Guide

## Overview
This guide explains how to set up Instant Payment Notification (IPN) URLs on your wifihyper.com server to receive payment confirmations from Yo Payments.

## 🎯 What are IPN URLs?

IPN URLs are webhook endpoints that Yo Payments calls to notify your system about payment status changes:
- **Success**: When a payment is completed successfully
- **Failure**: When a payment fails
- **Pending**: When a payment is being processed

## 🌐 URL Configuration

### 1. Update Your .env File

Add these lines to your `.env` file on the production server:

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

### 2. Server Requirements

Ensure your server can receive HTTP POST requests at these endpoints:

```bash
# Test if endpoints are accessible
curl -X POST https://wifihyper.com/payment/callback
curl -X POST https://wifihyper.com/payment/failed
```

## 🔧 Server Configuration

### Apache Configuration

If using Apache, ensure your `.htaccess` file allows POST requests:

```apache
# .htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Allow POST requests
<Limit POST>
    Require all granted
</Limit>
```

### Nginx Configuration

If using Nginx, ensure your configuration allows POST requests:

```nginx
# nginx.conf
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# Allow POST requests
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### Firewall Configuration

Ensure your server allows incoming HTTP/HTTPS traffic:

```bash
# Ubuntu/Debian
sudo ufw allow 80
sudo ufw allow 443

# CentOS/RHEL
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## 📡 Testing IPN Endpoints

### 1. Test Command

Run this command to test notification URL generation:

```bash
php artisan test:notification-urls
```

### 2. Manual Testing

Test the endpoints manually:

```bash
# Test success callback
curl -X POST https://wifihyper.com/payment/callback \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "transaction_id=TEST_123&status=success"

# Test failure callback
curl -X POST https://wifihyper.com/payment/failed \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "transaction_id=TEST_123&status=failed"
```

### 3. Check Logs

Monitor your Laravel logs for IPN requests:

```bash
tail -f storage/logs/laravel-*.log | grep "payment"
```

## 🔒 Security Considerations

### 1. CSRF Protection

IPN endpoints are already configured to bypass CSRF protection:

```php
// routes/web.php
Route::post('/payment/callback', [PaymentController::class, 'callback'])
    ->name('payment.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/payment/failed', [PaymentController::class, 'failed'])
    ->name('payment.failed.post')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

### 2. IP Whitelisting (Optional)

Consider whitelisting Yo Payments IP addresses:

```php
// In your PaymentController
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

### 3. Signature Verification

The system already implements signature verification for failure notifications as per Yo Payments API 6.4.

## 📊 Monitoring and Debugging

### 1. Enable Debug Logging

Add this to your `.env` file for detailed logging:

```bash
APP_DEBUG=true
LOG_LEVEL=debug
```

### 2. Monitor IPN Requests

Check your server access logs:

```bash
# Apache
tail -f /var/log/apache2/access.log | grep "payment"

# Nginx
tail -f /var/log/nginx/access.log | grep "payment"
```

### 3. Test IPN with Yo Payments

Contact Yo Payments support to enable IPN testing mode for your account.

## 🚀 Deployment Checklist

- [ ] Update `.env` file with IPN URLs
- [ ] Clear Laravel caches: `php artisan config:clear`
- [ ] Test endpoints are accessible
- [ ] Verify firewall allows HTTP/HTTPS traffic
- [ ] Test notification URL generation
- [ ] Monitor logs for IPN requests
- [ ] Test with Yo Payments sandbox (if available)

## 📞 Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel-*.log`
2. Verify server configuration
3. Test endpoints manually with curl
4. Contact Yo Payments support for IPN testing

## 🔗 Related Files

- `config/services.php` - IPN configuration
- `app/Services/YoPaymentsService.php` - IPN URL generation
- `routes/web.php` - IPN endpoint routes
- `app/Http/Controllers/PaymentController.php` - IPN handling 