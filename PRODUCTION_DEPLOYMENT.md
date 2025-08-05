# WIFIHYPER Production Deployment Guide

## 🚀 Production Deployment Checklist

This guide provides a comprehensive checklist for deploying WIFIHYPER to production.

## 📋 Pre-Deployment Checklist

### ✅ Environment Configuration

#### **Required Environment Variables**
```env
# Application
APP_NAME=WIFIHYPER
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=base64:your-app-key

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=wifihyper_production
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=redis

# Mail (Resend)
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME=WIFIHYPER
RESEND_KEY=your-resend-api-key

# SMS Gateway (UG SMS)
UG_SMS_USERNAME=your-ug-sms-username
UG_SMS_PASSWORD=your-ug-sms-password
UG_SMS_SENDER_ID=Wifihyper

# Payment Gateway (Yo! Payments)
YO_PAYMENTS_USERNAME=your-yo-payments-username
YO_PAYMENTS_PASSWORD=your-yo-payments-password
YO_PAYMENTS_BASE_URL=https://paymentsapi1.yo.co.ug/ybs/task.php
YO_PAYMENTS_FALLBACK_URL=https://paymentsapi2.yo.co.ug/ybs/task.php

# Security
SECURITY_LOGIN_ATTEMPTS=5
SECURITY_SESSION_TIMEOUT=30
SECURITY_PASSWORD_MIN_LENGTH=8
SECURITY_LOGGING_ENABLED=true
```

### ✅ Server Requirements

#### **Minimum Server Specifications**
- **CPU**: 2 cores
- **RAM**: 4GB
- **Storage**: 50GB SSD
- **OS**: Ubuntu 20.04+ or CentOS 8+
- **PHP**: 8.2+
- **MySQL**: 8.0+
- **Redis**: 6.0+
- **Nginx**: 1.18+

#### **Software Installation**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo apt install php8.2-fpm php8.2-mysql php8.2-redis php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip php8.2-gd -y

# Install MySQL
sudo apt install mysql-server -y

# Install Redis
sudo apt install redis-server -y

# Install Nginx
sudo apt install nginx -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### ✅ Database Setup

#### **MySQL Configuration**
```sql
-- Create database
CREATE DATABASE wifihyper_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'wifihyper_user'@'localhost' IDENTIFIED BY 'strong_password_here';

-- Grant privileges
GRANT ALL PRIVILEGES ON wifihyper_production.* TO 'wifihyper_user'@'localhost';
FLUSH PRIVILEGES;
```

#### **Redis Configuration**
```bash
# Edit Redis config
sudo nano /etc/redis/redis.conf

# Add/modify these settings:
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### ✅ Application Deployment

#### **1. Clone Repository**
```bash
cd /var/www
sudo git clone https://github.com/your-repo/wifi-billing-system.git wifihyper
sudo chown -R www-data:www-data wifihyper
cd wifihyper
```

#### **2. Install Dependencies**
```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

#### **3. Environment Setup**
```bash
cp .env.example .env
nano .env  # Configure all environment variables
php artisan key:generate
```

#### **4. Database Migration**
```bash
php artisan migrate --force
php artisan db:seed --force
```

#### **5. Storage Setup**
```bash
php artisan storage:link
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### ✅ Nginx Configuration

#### **Create Nginx Site Configuration**
```bash
sudo nano /etc/nginx/sites-available/wifihyper
```

#### **Nginx Configuration**
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    root /var/www/wifihyper/public;
    index index.php index.html index.htm;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### **Enable Site**
```bash
sudo ln -s /etc/nginx/sites-available/wifihyper /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### ✅ SSL Certificate (Let's Encrypt)

#### **Install Certbot**
```bash
sudo apt install certbot python3-certbot-nginx -y
```

#### **Obtain SSL Certificate**
```bash
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

#### **Auto-renewal**
```bash
sudo crontab -e
# Add this line:
0 12 * * * /usr/bin/certbot renew --quiet
```

### ✅ Queue Worker Setup

#### **Create Queue Worker Service**
```bash
sudo nano /etc/systemd/system/wifihyper-queue.service
```

#### **Queue Service Configuration**
```ini
[Unit]
Description=WIFIHYPER Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/wifihyper/artisan queue:work --sleep=3 --tries=3 --max-time=3600
RestartSec=5

[Install]
WantedBy=multi-user.target
```

#### **Enable Queue Worker**
```bash
sudo systemctl enable wifihyper-queue
sudo systemctl start wifihyper-queue
```

### ✅ Cron Jobs

#### **Setup Laravel Scheduler**
```bash
sudo crontab -e
# Add this line:
* * * * * cd /var/www/wifihyper && php artisan schedule:run >> /dev/null 2>&1
```

### ✅ Monitoring & Logging

#### **Install Monitoring Tools**
```bash
# Install htop for system monitoring
sudo apt install htop -y

# Install logrotate for log management
sudo apt install logrotate -y
```

#### **Log Rotation Configuration**
```bash
sudo nano /etc/logrotate.d/wifihyper
```

```conf
/var/www/wifihyper/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload nginx
    endscript
}
```

## 🔍 Pre-Deployment Testing

### **1. Test All Gateways**
```bash
php artisan gateways:test
```

### **2. Test Production Readiness**
```bash
php artisan gateways:test --production
```

### **3. Security Scan**
```bash
# Test security headers
curl -I https://your-domain.com

# Expected headers:
# X-Content-Type-Options: nosniff
# X-Frame-Options: SAMEORIGIN
# X-XSS-Protection: 1; mode=block
# Content-Security-Policy: default-src 'self'; ...
```

### **4. Performance Test**
```bash
# Install Apache Bench
sudo apt install apache2-utils -y

# Test performance
ab -n 1000 -c 10 https://your-domain.com/
```

## 🚀 Deployment Commands

### **Quick Deployment Script**
```bash
#!/bin/bash
# deploy.sh

echo "🚀 Starting WIFIHYPER Production Deployment..."

# Pull latest changes
git pull origin main

# Install dependencies
composer install --optimize-autoloader --no-dev
npm install
npm run build

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart wifihyper-queue
sudo systemctl reload nginx

echo "✅ Deployment completed successfully!"
```

### **Make Script Executable**
```bash
chmod +x deploy.sh
./deploy.sh
```

## 🔧 Post-Deployment Verification

### **1. Application Health Check**
```bash
# Test application
curl -f https://your-domain.com/up

# Test database connection
php artisan tinker --execute="echo 'Database: ' . (DB::connection()->getPdo() ? 'OK' : 'FAIL');"
```

### **2. Gateway Testing**
```bash
# Test all gateways
php artisan gateways:test

# Expected output:
# ✅ Email Gateway: Email gateway working correctly
# ✅ SMS Gateway: SMS gateway configured correctly
# ✅ Payment Gateway: Payment gateway configured correctly
```

### **3. Security Verification**
```bash
# Test security headers
curl -I https://your-domain.com

# Test rate limiting
for i in {1..10}; do curl -X POST https://your-domain.com/login; done
```

### **4. Performance Monitoring**
```bash
# Monitor system resources
htop

# Monitor logs
tail -f /var/www/wifihyper/storage/logs/laravel.log
tail -f /var/www/wifihyper/storage/logs/security.log
```

## 🛡️ Security Checklist

### ✅ **SSL/TLS Configuration**
- [ ] SSL certificate installed
- [ ] HTTPS redirect configured
- [ ] HSTS headers enabled
- [ ] SSL protocols configured (TLS 1.2+)

### ✅ **Server Security**
- [ ] Firewall configured (UFW)
- [ ] SSH key authentication
- [ ] Root login disabled
- [ ] Fail2ban installed

### ✅ **Application Security**
- [ ] Environment variables secured
- [ ] File permissions set correctly
- [ ] Security headers configured
- [ ] Rate limiting enabled

### ✅ **Database Security**
- [ ] Database user with minimal privileges
- [ ] Database connection encrypted
- [ ] Regular backups configured
- [ ] Database logs monitored

## 📊 Monitoring Setup

### **Application Monitoring**
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs -y

# Monitor application logs
tail -f /var/www/wifihyper/storage/logs/laravel.log
tail -f /var/www/wifihyper/storage/logs/security.log
```

### **System Monitoring**
```bash
# Monitor system resources
htop
df -h
free -h
```

### **Database Monitoring**
```bash
# Monitor MySQL
sudo mysql -u root -p
SHOW PROCESSLIST;
SHOW STATUS;
```

## 🔄 Backup Strategy

### **Database Backup**
```bash
# Create backup script
sudo nano /usr/local/bin/backup-wifihyper.sh
```

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/wifihyper"
DB_NAME="wifihyper_production"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u wifihyper_user -p $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# Application backup
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz /var/www/wifihyper

# Clean old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### **Setup Automated Backups**
```bash
chmod +x /usr/local/bin/backup-wifihyper.sh
sudo crontab -e
# Add this line for daily backups at 2 AM:
0 2 * * * /usr/local/bin/backup-wifihyper.sh
```

## 🚨 Emergency Procedures

### **Rollback Procedure**
```bash
# Rollback to previous version
cd /var/www/wifihyper
git log --oneline -10
git checkout <previous-commit-hash>
composer install --optimize-autoloader --no-dev
php artisan config:cache
sudo systemctl restart wifihyper-queue
```

### **Database Recovery**
```bash
# Restore database from backup
mysql -u wifihyper_user -p wifihyper_production < /var/backups/wifihyper/db_backup_YYYYMMDD_HHMMSS.sql
```

### **Emergency Contacts**
- **System Administrator**: admin@your-domain.com
- **Security Team**: security@your-domain.com
- **Hosting Provider**: support@hosting-provider.com

## 📈 Performance Optimization

### **PHP Optimization**
```bash
# Edit PHP configuration
sudo nano /etc/php/8.2/fpm/php.ini

# Optimize these settings:
memory_limit = 512M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
```

### **MySQL Optimization**
```bash
# Edit MySQL configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Add these optimizations:
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 64M
max_connections = 200
```

### **Redis Optimization**
```bash
# Edit Redis configuration
sudo nano /etc/redis/redis.conf

# Optimize these settings:
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

## ✅ Final Verification

### **Production Readiness Test**
```bash
php artisan gateways:test --production
```

### **Expected Output**
```
🎉 Production Ready! All systems are configured correctly.
✅ Environment: Environment configured correctly
✅ Database: Database connection successful
✅ Storage: Storage configured correctly
✅ Cache: Cache working correctly
✅ Security: Security configured correctly
✅ Email Gateway: Email gateway working correctly
✅ SMS Gateway: SMS gateway configured correctly
✅ Payment Gateway: Payment gateway configured correctly
```

---

**🎉 Congratulations! Your WIFIHYPER application is now ready for production!**

**Last Updated**: August 2025
**Version**: 1.0
**Status**: Production Ready 