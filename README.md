# 🌐 WIFIHYPER - WiFi Billing System

A comprehensive WiFi hotspot billing and voucher management system built with Laravel, featuring mobile payments, SMS notifications, and automated voucher distribution.

## 📋 Table of Contents

- [Features](#-features)
- [System Overview](#-system-overview)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Database Structure](#-database-structure)
- [Payment Flow](#-payment-flow)
- [Mobile Responsiveness](#-mobile-responsiveness)
- [Production Deployment](#-production-deployment)
- [Security](#-security)
- [API Integrations](#-api-integrations)
- [Troubleshooting](#-troubleshooting)
- [Changelog](#-changelog)

## ✨ Features

### 🏢 Multi-Tenant Architecture
- **Tenant Management**: Separate billing and data for each hotspot owner
- **Subscription Plans**: Starter, Pro, and Enterprise tiers
- **Plan Limits**: Automatic enforcement of package and hotspot limits
- **Isolated Data**: Each tenant's data is completely separated

### 💳 Payment System
- **JPesa Integration**: Secure mobile money payments
- **Automatic Voucher Distribution**: SMS delivery of WiFi codes
- **Transaction Tracking**: Complete payment history and status
- **Fee Calculation**: Automatic transaction fee handling

### 📱 User Experience
- **Captive Portal**: Beautiful, responsive WiFi login page
- **Real-time Availability**: Live voucher stock checking
- **Mobile Responsive**: Works perfectly on all devices
- **Phone Number Formatting**: Automatic international format conversion

### 🔧 Management Features
- **Hotspot Management**: Create and manage multiple hotspots
- **Package Creation**: Flexible WiFi package configuration
- **Voucher Management**: Bulk upload and tracking
- **Analytics Dashboard**: Sales and usage statistics

## 🏗️ System Overview

### Architecture
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Captive Portal│    │  Admin Dashboard│    │  Payment System │
│   (User Access) │    │  (Management)   │    │  (JPesa)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │  Laravel Backend│
                    │  (API & Logic)  │
                    └─────────────────┘
                                 │
                    ┌─────────────────┐
                    │   MySQL Database│
                    │  (Data Storage) │
                    └─────────────────┘
```

### Key Components
- **Frontend**: Bootstrap 5, responsive design
- **Backend**: Laravel 10, PHP 8.1+
- **Database**: MySQL with proper relationships
- **Payments**: JPesa API integration
- **SMS**: UGSMS API for voucher delivery
- **Email**: Resend API for notifications

## 🚀 Installation

### Prerequisites
- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer
- Node.js (for asset compilation)

### Quick Setup

1. **Clone the repository**
```bash
git clone <repository-url>
cd wifi-billing-system
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database setup**
```bash
php artisan migrate
php artisan db:seed --class=SubscriptionPlanSeeder
```

5. **Build assets**
```bash
npm run build
```

6. **Start development server**
```bash
php artisan serve
```

## ⚙️ Configuration

### Environment Variables

#### Basic Configuration
```env
APP_NAME=WIFIHYPER
APP_ENV=local
APP_DEBUG=true
APP_URL=https://wifihyper.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wifi_billing
DB_USERNAME=root
DB_PASSWORD=
```

#### Payment API Configuration
```env
# JPesa Payment Gateway Configuration
JPESA_ENABLED=true
JPESA_API_KEY=your_jpesa_api_key
JPESA_BASE_URL=https://my.jpesa.com/api/
JPESA_CALLBACK_URL=https://wifihyper.com/payment/jpesa/callback
JPESA_TIMEOUT=400
JPESA_TEST_MODE=false
JPESA_TEST_PHONE_PREFIX=256700
```

#### SMS API Configuration
```env
UG_SMS_API_URL=https://api.ug.sms.com/send
UG_SMS_USERNAME=your_sms_username
UG_SMS_PASSWORD=your_sms_password
```

#### Email Configuration
```env
RESEND_API_KEY=your_resend_api_key
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

### Production Settings
```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

## 🗄️ Database Structure

### Core Tables

#### Tenants
- **Purpose**: Multi-tenant architecture
- **Key Fields**: name, email, subscription_plan_id
- **Relationships**: hasMany hotspots, hasMany vouchers

#### Hotspots
- **Purpose**: WiFi access points
- **Key Fields**: name, ssid, location, tenant_id
- **Relationships**: belongsTo tenant, hasMany packages

#### Packages
- **Purpose**: WiFi service offerings
- **Key Fields**: name, price, duration_hours, data_limit_mb
- **Relationships**: belongsTo hotspot, hasMany vouchers

#### Vouchers
- **Purpose**: WiFi access codes
- **Key Fields**: code, status, phone_number, expires_at
- **Relationships**: belongsTo package, belongsTo tenant

#### Transactions
- **Purpose**: Payment tracking
- **Key Fields**: amount, status, phone_number, reference
- **Relationships**: belongsTo tenant, belongsTo package

### Database Relationships
```
Tenant (1) ── (Many) Hotspots
Hotspot (1) ── (Many) Packages
Package (1) ── (Many) Vouchers
Tenant (1) ── (Many) Transactions
```

## 💳 Payment Flow

### Complete Payment Process

1. **User Access**
   - User connects to WiFi hotspot
   - Captive portal displays available packages

2. **Package Selection**
   - User selects WiFi package
   - System checks voucher availability
   - Real-time stock validation

3. **Payment Initiation**
   - User enters phone number (local format: 0744744888)
   - System converts to international format (256744744888)
   - Payment request sent to JPesa

4. **Payment Processing**
   - User completes payment via mobile money
   - JPesa sends callback to system
   - Transaction status updated

5. **Voucher Delivery**
   - Available voucher assigned to transaction
   - SMS sent with WiFi code
   - User receives voucher code

6. **WiFi Access**
   - User enters voucher code
   - WiFi access granted
   - Usage tracking begins

### Phone Number Handling
- **Input Format**: Local format (0744744888, 0397373763)
- **Conversion**: Automatic to international (256XXXXXXXX)
- **Validation**: Uganda mobile number validation
- **Error Handling**: Clear user feedback

## 📱 Mobile Responsiveness

### Responsive Design Features

#### Landing Page
- **Hero Section**: Full-height responsive design
- **Feature Cards**: Adaptive grid layout
- **Footer**: Gradient background with brand colors
- **Navigation**: Mobile-friendly menu

#### Dashboard
- **Sidebar**: Collapsible on mobile devices
- **Tables**: Horizontal scrolling on small screens
- **Forms**: Touch-friendly input fields
- **Buttons**: Proper touch targets (44px minimum)

#### Captive Portal
- **Package Cards**: Responsive grid layout
- **Payment Modal**: Mobile-optimized form
- **Phone Input**: Touch-friendly number pad
- **Loading States**: Visual feedback during processing

### CSS Media Queries
```css
/* Mobile devices */
@media (max-width: 768px) {
    .portal-container { padding: 10px; }
    .package-card { margin-bottom: 10px; }
    .btn-buy-now { padding: 12px 20px; }
}

/* Tablet devices */
@media (max-width: 1024px) {
    .dashboard-sidebar { position: fixed; }
    .main-content { margin-left: 0; }
}
```

## 🚀 Production Deployment

### Deployment Checklist

#### Environment Setup
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure real API credentials
- [ ] Set up SSL certificate
- [ ] Configure database backups

#### File Permissions
```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/
```

#### Cache Clearing
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

#### Database Setup
```bash
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --force
```

### Deployment Script
```bash
#!/bin/bash
echo '🚀 Deploying WIFIHYPER to production...'

# Set file permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Run migrations
php artisan migrate --force

# Seed admin accounts
php artisan db:seed --class=AdminSeeder --force

# Seed other data if needed
php artisan db:seed --force

echo '✅ Deployment complete!'
```

## 👤 Admin Management

### Creating Admin Users

#### Method 1: Using Environment Variables (Recommended for Production)

Set the following environment variables in your `.env` file:

```env
# Primary Admin
ADMIN_NAME="Your Name"
ADMIN_EMAIL="admin@yourdomain.com"
ADMIN_PASSWORD="your_secure_password"

# Secondary Admin (Optional)
ADMIN_NAME_2="Secondary Admin"
ADMIN_EMAIL_2="admin2@yourdomain.com"
ADMIN_PASSWORD_2="another_secure_password"
```

Then run the seeder:
```bash
php artisan db:seed --class=AdminSeeder --force
```

#### Method 2: Using Artisan Command

Create admin users interactively:
```bash
# Interactive mode
php artisan admin:create

# Non-interactive mode
php artisan admin:create \
  --name="Admin Name" \
  --email="admin@example.com" \
  --password="secure_password" \
  --role="super_admin" \
  --force
```

#### Method 3: Production Deployment

The deployment script automatically:
1. Generates secure random passwords
2. Creates admin accounts using provided email
3. Displays credentials at the end of deployment

### Admin Roles

- **admin**: Regular admin access
- **super_admin**: Full system access

### Security Best Practices

1. **Change Default Passwords**: Always change default passwords in production
2. **Use Strong Passwords**: Minimum 12 characters with mixed case, numbers, and symbols
3. **Environment Variables**: Store credentials in `.env` file, never in code
4. **Regular Updates**: Regularly update admin passwords
5. **Monitor Access**: Check admin login logs regularly

## 🔒 Security

### Security Features

#### Authentication & Authorization
- **Session Management**: Secure session handling
- **CSRF Protection**: Built-in Laravel CSRF tokens
- **Input Validation**: Comprehensive form validation
- **SQL Injection Prevention**: Eloquent ORM protection

#### Data Protection
- **Environment Variables**: Sensitive data in .env
- **Database Encryption**: Sensitive fields encrypted
- **API Key Security**: Secure API credential storage
- **HTTPS Enforcement**: SSL certificate required

#### Payment Security
- **JPesa Integration**: Secure payment gateway
- **Transaction Validation**: Server-side payment verification
- **Callback Verification**: Secure payment confirmation
- **Error Handling**: Comprehensive error logging

### Security Checklist
- [ ] SSL certificate installed
- [ ] HTTPS enabled
- [ ] File permissions set correctly
- [ ] Database credentials secured
- [ ] API keys protected
- [ ] Error logging configured
- [ ] Regular security updates
- [ ] Database backups configured

## 🔌 API Integrations

### JPesa API
```php
// Payment initiation
$paymentData = [
    'amount' => 1000,
    'phone_number' => '256744744888',
    'reference' => 'TXN_' . time(),
    'description' => 'WiFi voucher payment'
];

$response = $jpesa->initiatePayment($paymentData);
```

### UGSMS API
```php
// SMS sending
$smsData = [
    'phone_number' => '256744744888',
    'message' => 'Your WiFi code: ABC123'
];

$response = $smsService->sendVoucherCode($smsData);
```

### Resend Email API
```php
// Email sending
$emailData = [
    'to' => 'user@example.com',
    'subject' => 'WiFi Voucher',
    'html' => '<p>Your WiFi code: ABC123</p>'
];

$response = $resend->sendEmail($emailData);
```

## 🛠️ Troubleshooting

### Common Issues

#### Package Creation Fails
**Problem**: Database error creating package
**Solution**: 
1. Check for empty string values in integer fields
2. Ensure sort_order is never null
3. Verify hotspot_id exists
4. Check database permissions

#### Payment Flow Issues
**Problem**: Payment initiation fails
**Solution**:
1. Verify JPesa API credentials
2. Check phone number format (256XXXXXXXX)
3. Ensure voucher availability
4. Check network connectivity

#### SMS Not Sending
**Problem**: Voucher codes not delivered
**Solution**:
1. Check UGSMS API credentials
2. Verify phone number format
3. Check SMS balance
4. Review API response logs

#### Mobile Responsiveness Issues
**Problem**: Layout breaks on mobile
**Solution**:
1. Check Bootstrap CSS loading
2. Verify viewport meta tag
3. Test media queries
4. Check JavaScript errors

### Debug Commands
```bash
# Check application status
php artisan route:list
php artisan config:cache
php artisan view:cache

# Database checks
php artisan migrate:status
php artisan db:show

# Clear all caches
php artisan optimize:clear
```

### Log Files
- **Application Logs**: `storage/logs/laravel.log`
- **Payment Logs**: Check JPesa dashboard
- **SMS Logs**: Check UGSMS dashboard
- **Error Logs**: `storage/logs/error.log`

## 📈 Performance Optimization

### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_transactions_status ON transactions(status);
CREATE INDEX idx_vouchers_package_status ON vouchers(package_id, status);
CREATE INDEX idx_packages_hotspot_active ON packages(hotspot_id, is_active);
```

### Caching Strategy
```php
// Cache frequently accessed data
Cache::remember('package_availability', 300, function() {
    return VoucherAvailabilityService::getAvailability();
});
```

### Queue Processing
```php
// Use queues for SMS and email sending
dispatch(new SendVoucherSms($transaction));
dispatch(new SendPaymentNotification($transaction));
```

## 📊 Monitoring & Analytics

### Key Metrics
- **Payment Success Rate**: Track successful transactions
- **SMS Delivery Rate**: Monitor voucher delivery
- **Voucher Usage**: Track WiFi access patterns
- **Revenue Analytics**: Sales and profit tracking

### Logging Strategy
```php
// Payment success logging
Log::info('Payment successful', [
    'transaction_id' => $transactionId,
    'amount' => $amount,
    'package' => $packageName,
]);

// Error logging
Log::error('Payment failed', [
    'transaction_id' => $transactionId,
    'error' => $error,
    'user_agent' => $request->userAgent(),
    'ip' => $request->ip(),
]);
```

## 🔄 Changelog

### Version 1.0.0 (Latest)
- ✅ **Multi-tenant architecture** implemented
- ✅ **Payment system** with JPesa integration
- ✅ **SMS delivery** with UGSMS integration
- ✅ **Mobile responsive** design
- ✅ **Captive portal** with real-time availability
- ✅ **Package creation** with enhanced validation
- ✅ **Phone number formatting** with automatic conversion
- ✅ **Production deployment** guide
- ✅ **Security features** implemented
- ✅ **Database optimization** completed

### Key Fixes
- **Package Creation**: Fixed empty string handling for integer fields
- **Phone Number Input**: Enhanced user experience with local format input
- **Mobile Responsiveness**: Improved touch targets and layout
- **Payment Flow**: Robust error handling and validation
- **Database Structure**: Proper foreign key relationships

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:
- **Email**: support@wifihyper.com
- **Documentation**: Check the guides above
- **Issues**: Use GitHub issues for bug reports

---

**WIFIHYPER** - Professional WiFi Billing System  
**Version**: 1.0.0  
**Status**: Production Ready ✅  
**Last Updated**: August 2025
