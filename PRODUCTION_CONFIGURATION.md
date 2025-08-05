# WIFIHYPER Production Configuration Guide

## 🔧 Environment Variables for Production

Copy these variables to your `.env` file and configure with your actual values:

### **Application Configuration**
```env
APP_NAME=WIFIHYPER
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=base64:your-app-key-here
```

### **Database Configuration**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wifihyper_production
DB_USERNAME=wifihyper_user
DB_PASSWORD=your-strong-database-password
```

### **Cache & Session Configuration**
```env
CACHE_DRIVER=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=redis
```

### **Redis Configuration**
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### **Mail Configuration (Resend)**
```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME=WIFIHYPER
RESEND_KEY=your-resend-api-key-here
```

### **SMS Gateway Configuration (UG SMS)**
```env
UG_SMS_USERNAME=your-ug-sms-username
UG_SMS_PASSWORD=your-ug-sms-password
UG_SMS_SENDER_ID=Wifihyper
```

### **Payment Gateway Configuration (Yo! Payments)**
```env
YO_PAYMENTS_USERNAME=your-yo-payments-username
YO_PAYMENTS_PASSWORD=your-yo-payments-password
YO_PAYMENTS_BASE_URL=https://paymentsapi1.yo.co.ug/ybs/task.php
YO_PAYMENTS_FALLBACK_URL=https://paymentsapi2.yo.co.ug/ybs/task.php
YO_PAYMENTS_PUBLIC_KEY_ENABLED=false
YO_PAYMENTS_PRIVATE_KEY_PATH=storage/keys/yo_payments_private_key.pem
```

### **Security Configuration**
```env
SECURITY_LOGIN_ATTEMPTS=5
SECURITY_LOGIN_DECAY_MINUTES=1
SECURITY_API_REQUESTS=60
SECURITY_API_DECAY_MINUTES=1
SECURITY_SESSION_TIMEOUT=30
SECURITY_SESSION_REGENERATE_INTERVAL=300
SECURITY_SECURE_COOKIES=true
SECURITY_HTTP_ONLY_COOKIES=true
SECURITY_PASSWORD_MIN_LENGTH=8
SECURITY_PASSWORD_REQUIRE_UPPERCASE=true
SECURITY_PASSWORD_REQUIRE_LOWERCASE=true
SECURITY_PASSWORD_REQUIRE_NUMBERS=true
SECURITY_PASSWORD_REQUIRE_SPECIAL_CHARS=true
SECURITY_PASSWORD_MAX_AGE_DAYS=90
SECURITY_LOGGING_ENABLED=true
SECURITY_LOGGING_CHANNEL=security
SECURITY_MAX_FILE_SIZE_KB=1024
SECURITY_SCAN_UPLOADS=true
```

### **Logging Configuration**
```env
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=30
```

### **File Upload Configuration**
```env
FILESYSTEM_DISK=local
```

### **Queue Configuration**
```env
QUEUE_FAILED_DRIVER=database-uuids
```

### **Session Configuration**
```env
SESSION_LIFETIME=30
SESSION_EXPIRE_ON_CLOSE=true
```

### **Timezone Configuration**
```env
APP_TIMEZONE=Africa/Kampala
```

### **Maintenance Mode**
```env
APP_MAINTENANCE=false
```

### **Trusted Proxies**
```env
TRUSTED_PROXIES=*
```

### **Rate Limiting**
```env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_ATTEMPTS=5
RATE_LIMIT_DECAY_MINUTES=1
```

### **Backup Configuration**
```env
BACKUP_ENABLED=true
BACKUP_RETENTION_DAYS=30
BACKUP_TIME=02:00
```

### **Monitoring Configuration**
```env
MONITORING_ENABLED=true
MONITORING_HEALTH_CHECK_URL=/up
```

### **Development Overrides**
```env
DEVELOPMENT_MODE=false
DEBUG_MODE=false
```

## 🔑 API Keys Setup

### **1. Resend Email Setup**
1. Go to [Resend.com](https://resend.com)
2. Create an account
3. Add your domain or use the default domain
4. Get your API key
5. Add to `.env`: `RESEND_KEY=your-api-key`

### **2. UG SMS Setup**
1. Contact UG SMS provider
2. Get your username and password
3. Add to `.env`:
   - `UG_SMS_USERNAME=your-username`
   - `UG_SMS_PASSWORD=your-password`

### **3. Yo! Payments Setup**
1. Contact Yo! Payments
2. Get your credentials
3. Add to `.env`:
   - `YO_PAYMENTS_USERNAME=your-username`
   - `YO_PAYMENTS_PASSWORD=your-password`

## 🔍 Configuration Testing

### **Test All Gateways**
```bash
php artisan gateways:test
```

### **Test Production Readiness**
```bash
php artisan gateways:test --production
```

### **Expected Results**
```
✅ Email Gateway: Email gateway working correctly
✅ SMS Gateway: SMS gateway configured correctly
✅ Payment Gateway: Payment gateway configured correctly
🎉 Production Ready! All systems are configured correctly.
```

## 🚨 Security Checklist

### **Environment Variables**
- [ ] All API keys are set
- [ ] Database credentials are secure
- [ ] APP_DEBUG is set to false
- [ ] APP_ENV is set to production
- [ ] HTTPS is configured

### **Gateway Configuration**
- [ ] Resend API key is valid
- [ ] UG SMS credentials are working
- [ ] Yo! Payments credentials are working
- [ ] All gateways pass the test command

### **Security Features**
- [ ] Rate limiting is enabled
- [ ] Session security is configured
- [ ] Security headers are active
- [ ] Input sanitization is working

## 📊 Monitoring Setup

### **Health Check Endpoint**
```bash
# Test application health
curl -f https://your-domain.com/up
```

### **Gateway Monitoring**
```bash
# Monitor gateway status
php artisan gateways:test --production
```

### **Log Monitoring**
```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor security logs
tail -f storage/logs/security.log
```

## 🔄 Backup Configuration

### **Database Backup**
```bash
# Create backup directory
mkdir -p /var/backups/wifihyper

# Create backup script
nano /usr/local/bin/backup-wifihyper.sh
```

### **Backup Script Content**
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
crontab -e
# Add this line for daily backups at 2 AM:
0 2 * * * /usr/local/bin/backup-wifihyper.sh
```

## ✅ Final Verification

### **1. Test All Gateways**
```bash
php artisan gateways:test
```

### **2. Test Production Readiness**
```bash
php artisan gateways:test --production
```

### **3. Test Security Headers**
```bash
curl -I https://your-domain.com
```

### **4. Test Application Health**
```bash
curl -f https://your-domain.com/up
```

## 🎯 Success Criteria

Your WIFIHYPER application is production-ready when:

1. ✅ All gateways pass the test command
2. ✅ Production readiness check passes
3. ✅ Security headers are properly configured
4. ✅ SSL certificate is installed
5. ✅ Database backups are automated
6. ✅ Monitoring is set up
7. ✅ All environment variables are configured
8. ✅ Application health check passes

---

**🎉 Congratulations! Your WIFIHYPER application is now ready for production deployment!**

**Last Updated**: August 2025
**Version**: 1.0
**Status**: Production Ready 