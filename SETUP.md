# WiFi SaaS - Setup Guide

## 🚀 Quick Start

This guide will help you set up the WiFi SaaS application for development and production.

## 📋 Prerequisites

- PHP 8.2 or higher
- MySQL 5.7 or higher (or SQLite for development)
- Composer
- Node.js and npm (for frontend assets)
- Git

## 🛠️ Installation Steps

### 1. Clone the Repository
```bash
git clone <your-repository-url>
cd wifi-billing-system
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Database Setup
```bash
# Run migrations
php artisan migrate

# (Optional) Seed with test data
php artisan db:seed
```

### 5. Storage Setup
```bash
# Create storage links
php artisan storage:link

# Set proper permissions (Linux/Mac)
chmod -R 775 storage bootstrap/cache
```

### 6. Build Frontend Assets
```bash
npm run build
```

## 🔧 Configuration

### Environment Variables

Update your `.env` file with the following configurations:

#### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wifi_saas
DB_USERNAME=root
DB_PASSWORD=your_password
```

#### Yo Payments Configuration
```env
YO_PAYMENTS_BASE_URL=https://paymentsapi1.yo.co.ug/ybs/task.php
YO_PAYMENTS_FALLBACK_URL=https://paymentsapi2.yo.co.ug/ybs/task.php
YO_PAYMENTS_USERNAME=your_yo_payments_username
YO_PAYMENTS_PASSWORD=your_yo_payments_password
YO_PAYMENTS_PRIVATE_KEY_PATH=storage/keys/yo_payments_private_key.pem
YO_PAYMENTS_PUBLIC_KEY_ENABLED=false
YO_PAYMENTS_ENVIRONMENT=sandbox
```

#### UG SMS Configuration
```env
UG_SMS_BASE_URL=https://ugsms.com/v1/sms/send
UG_SMS_USERNAME=your_ug_sms_username
UG_SMS_PASSWORD=your_ug_sms_password
UG_SMS_SENDER_ID=WiFiSaaS
UG_SMS_ENVIRONMENT=sandbox
```

#### Transaction Fee Configuration
```env
TRANSACTION_FEE_PERCENTAGE=2.5
TRANSACTION_FEE_MINIMUM=100
TRANSACTION_FEE_MAXIMUM=5000
```

### SSL Certificate Setup

For Yo Payments integration, you need SSL certificates:

1. Generate private key:
```bash
# Using the provided script
./generate-keys.sh

# Or manually
openssl genrsa -out storage/keys/yo_payments_private_key.pem 2048
```

2. Update the private key path in your `.env` file:
```env
YO_PAYMENTS_PRIVATE_KEY_PATH=storage/keys/yo_payments_private_key.pem
```

## 🚀 Development

### Start Development Server
```bash
php artisan serve
```

### Watch for Frontend Changes
```bash
npm run dev
```

### Run Tests
```bash
php artisan test
```

## 🌐 Production Deployment

### 1. Environment Setup
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 2. Optimize Application
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Set Permissions
```bash
chmod -R 755 storage bootstrap/cache
```

### 4. Configure Web Server

#### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/wifi-billing-system/public
    
    <Directory /path/to/wifi-billing-system/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/wifi-billing-system/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    
    index index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 🔒 Security Considerations

### 1. Environment Variables
- Never commit `.env` files to version control
- Use strong, unique passwords for all services
- Rotate API keys regularly

### 2. SSL Certificates
- Keep private keys secure
- Use HTTPS in production
- Regularly update SSL certificates

### 3. Database Security
- Use strong database passwords
- Limit database user permissions
- Regular backups

### 4. File Permissions
- Restrict access to storage directories
- Set proper ownership for web server

## 📊 Monitoring

### Log Files
- Application logs: `storage/logs/laravel.log`
- Payment logs: Check Yo Payments dashboard
- SMS logs: Check UG SMS dashboard

### Health Checks
```bash
# Check application status
php artisan about

# Check queue status
php artisan queue:work --once

# Check storage permissions
ls -la storage/
```

## 🐛 Troubleshooting

### Common Issues

1. **Permission Denied**
```bash
chmod -R 775 storage bootstrap/cache
```

2. **Composer Dependencies**
```bash
composer install --no-dev --optimize-autoloader
```

3. **Database Connection**
```bash
php artisan config:clear
php artisan cache:clear
```

4. **Payment Integration**
- Verify Yo Payments credentials
- Check SSL certificate validity
- Test in sandbox mode first

5. **SMS Delivery**
- Verify UG SMS credentials
- Check SMS balance
- Test with small amounts first

## 📞 Support

For issues and questions:
- Check the logs in `storage/logs/`
- Review the README.md file
- Create an issue in the repository

## 🔄 Updates

To update the application:

```bash
# Pull latest changes
git pull origin main

# Install new dependencies
composer install

# Run migrations
php artisan migrate

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Rebuild assets
npm run build
```

---

**WiFi SaaS** - Making WiFi management simple and profitable! 🚀 