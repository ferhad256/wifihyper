# Environment Configuration Guide

## 📝 **Create your `.env` file**

Copy this content to your `.env` file in the project root:

```env
APP_NAME="WIFIHYPER"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wifi_billing
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=resend
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="onboarding@resend.dev"
MAIL_FROM_NAME="${APP_NAME}"

# Resend Configuration
RESEND_API_KEY=re_your_api_key_here

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

# Yo Payments Configuration
YO_PAYMENTS_API_KEY=your_yo_payments_api_key_here
YO_PAYMENTS_MERCHANT_ID=your_merchant_id_here

# UG SMS Configuration
UG_SMS_API_KEY=your_ug_sms_api_key_here
UG_SMS_SENDER_ID=your_sender_id_here
```

## 🔑 **Required API Keys**

### **1. Resend API Key (Required for Email)**
- **Get from**: [resend.com](https://resend.com)
- **Format**: `re_1234567890abcdef...`
- **Usage**: Email notifications, welcome emails, test emails

### **2. Yo Payments API Key (Optional)**
- **Get from**: Yo Payments dashboard
- **Usage**: Mobile money payments

### **3. UG SMS API Key (Optional)**
- **Get from**: UG SMS provider
- **Usage**: SMS notifications

## ⚙️ **Configuration Steps**

### **Step 1: Create `.env` file**
1. Create a new file named `.env` in the project root
2. Copy the configuration above into it

### **Step 2: Generate Laravel App Key**
```bash
php artisan key:generate
```

### **Step 3: Get Resend API Key**
1. Go to [resend.com](https://resend.com)
2. Sign up for free account (3,000 emails/month)
3. Go to "API Keys" section
4. Create new API key
5. Copy the key and replace `re_your_api_key_here`

### **Step 4: Update Database Settings**
- Change `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` to match your database

### **Step 5: Update Mail Settings (Optional)**
- The default uses Resend's domain: `onboarding@resend.dev`
- If you have a custom domain, change `MAIL_FROM_ADDRESS` to your domain email
- Example: `noreply@yourdomain.com`

## 🧪 **Test Configuration**

### **Test Email Setup:**
1. Start your Laravel server: `php artisan serve`
2. Go to Settings page in admin dashboard
3. Click "Test Email Configuration"
4. Check your email for test message

### **Test Database:**
```bash
php artisan migrate:status
```

## 🔒 **Security Notes**

- **Never commit `.env` file to Git**
- **Keep API keys secret**
- **Use different keys for development/production**
- **Rotate API keys regularly**

## 📋 **Configuration Checklist**

- [ ] `.env` file created
- [ ] Laravel app key generated
- [ ] Resend API key added
- [ ] Database settings configured
- [ ] Mail settings updated
- [ ] Email test successful
- [ ] Database migrations run

## 🆘 **Troubleshooting**

### **"APP_KEY not set" error:**
```bash
php artisan key:generate
```

### **"Resend API key not found" error:**
- Check `.env` file exists
- Verify API key format (starts with `re_`)
- Restart Laravel server after changes

### **"Database connection failed" error:**
- Check database credentials in `.env`
- Ensure database server is running
- Verify database exists

---

**Next Steps:**
1. Create `.env` file with the configuration above
2. Get your Resend API key from resend.com
3. Update the configuration with your actual keys
4. Test the email functionality 