# Resend Email Setup Guide

## 📧 **Step-by-Step Setup Instructions**

### **Step 1: Get Resend API Key**

1. **Sign up for Resend:**
   - Go to [resend.com](https://resend.com)
   - Create a free account (3,000 emails/month free)
   - Verify your email address

2. **Get your API Key:**
   - Log into your Resend dashboard
   - Go to "API Keys" section
   - Create a new API key
   - Copy the API key (starts with `re_`)

### **Step 2: Configure Environment Variables**

Add these to your `.env` file:

```env
# Mail Configuration
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="WIFIHYPER"

# Resend Configuration
RESEND_API_KEY=re_your_api_key_here
```

### **Step 3: Verify Domain (Optional but Recommended)**

1. **Add Domain to Resend:**
   - In Resend dashboard, go to "Domains"
   - Add your domain (e.g., `yourdomain.com`)
   - Follow the DNS setup instructions

2. **Update From Address:**
   - Change `MAIL_FROM_ADDRESS` to use your verified domain
   - Example: `noreply@yourdomain.com`
   - **Note**: Without a custom domain, emails will be sent from `onboarding@resend.dev`

### **Step 4: Test Email Configuration**

1. **Go to Settings Page:**
   - Login to your admin dashboard
   - Navigate to Settings page
   - Click "Test Email Configuration" button

2. **Check Your Email:**
   - Look for a test email in your inbox
   - If received, configuration is working correctly

## 🔧 **Features Implemented**

### **Email Templates Created:**
- ✅ **Welcome Email**: Sent to new tenants
- ✅ **Notification Email**: For system notifications
- ✅ **Test Email**: For configuration testing

### **Email Service Features:**
- ✅ **Notification Emails**: Low stock alerts, transaction notifications
- ✅ **Welcome Emails**: New tenant onboarding
- ✅ **Transaction Summaries**: Daily/weekly reports
- ✅ **Password Reset**: Account security
- ✅ **Test Configuration**: Verify setup

### **Settings Integration:**
- ✅ **Email Notifications Toggle**: Enable/disable email notifications
- ✅ **Test Email Button**: Verify configuration
- ✅ **Notification Frequency**: Control email frequency

## 📋 **Configuration Checklist**

- [ ] **Resend Account**: Created and verified
- [ ] **API Key**: Obtained and configured
- [ ] **Domain**: Added to Resend (optional)
- [ ] **Environment Variables**: Added to `.env`
- [ ] **Test Email**: Sent and received successfully
- [ ] **Email Notifications**: Enabled in settings

## 🚀 **Usage Examples**

### **Send Welcome Email:**
```php
$emailService = new EmailService();
$emailService->sendWelcomeEmail($tenant);
```

### **Send Notification Email:**
```php
$emailService = new EmailService();
$emailService->sendNotificationEmail($tenant, $notification);
```

### **Send Low Voucher Alert:**
```php
$emailService = new EmailService();
$emailService->sendLowVoucherAlert($tenant, 'Package Name', 3);
```

## 🔍 **Troubleshooting**

### **Common Issues:**

1. **"Failed to send test email"**
   - Check API key is correct
   - Verify domain is configured
   - Check Resend dashboard for errors

2. **"Unauthorized" error**
   - Verify API key permissions
   - Check if domain is verified

3. **Emails not sending**
   - Check mail configuration in `.env`
   - Verify Resend account is active
   - Check application logs

### **Debug Steps:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify Resend dashboard for delivery status
3. Test with simple mail configuration first
4. Check domain DNS settings

## 📊 **Resend Dashboard Features**

- **Email Analytics**: Track delivery rates
- **Domain Management**: Manage verified domains
- **API Usage**: Monitor email usage
- **Webhooks**: Real-time delivery updates
- **Templates**: Create reusable email templates

## 💡 **Best Practices**

1. **Use Verified Domains**: Improves deliverability
2. **Monitor Bounce Rates**: Keep them low
3. **Test Regularly**: Verify configuration works
4. **Use Templates**: Consistent branding
5. **Track Analytics**: Monitor email performance

## 🆘 **Support**

- **Resend Documentation**: [docs.resend.com](https://docs.resend.com)
- **Laravel Mail Docs**: [laravel.com/docs/mail](https://laravel.com/docs/mail)
- **Application Logs**: Check `storage/logs/` for errors

---

**Next Steps:**
1. Configure your `.env` file with Resend credentials
2. Test the email configuration
3. Enable email notifications in settings
4. Monitor email delivery in Resend dashboard 