# JPesa Callback Configuration Guide

## 🔗 **Callback URL Configuration**

### **Production Callback URL**
```
https://yourdomain.com/payment/jpesa/callback
```

### **Development Callback URL**
```
http://localhost:8000/payment/jpesa/callback
```

## ⚙️ **Environment Configuration**

### **Required Environment Variables**
```env
# JPesa Configuration
JPESA_ENABLED=true
JPESA_API_KEY=your_jpesa_api_key
JPESA_BASE_URL=https://my.jpesa.com/api/
JPESA_CALLBACK_URL=https://yourdomain.com/payment/jpesa/callback
JPESA_TIMEOUT=400
JPESA_TEST_MODE=false
```

### **Callback URL Requirements**
1. **HTTPS Required**: Production callbacks must use HTTPS
2. **Publicly Accessible**: JPesa must be able to reach your server
3. **No Authentication**: Callback endpoint bypasses CSRF protection
4. **POST Method**: JPesa sends POST requests to the callback URL

## 📡 **Callback Data Format**

### **JPesa Callback Parameters**
```json
{
    "tx": "YOUR_TRANSACTION_ID",
    "tid": "JPESA_TRANSACTION_ID", 
    "api_status": "success|error",
    "msg": "Status message",
    "memo": "Memo number",
    "_api_log_": "API log reference"
}
```

### **Callback Response**
- **Success**: Return HTTP 200 with "OK"
- **Error**: Return HTTP 400/500 with "ERROR"

## 🔍 **Callback Monitoring**

### **Test Callback Endpoint**
```bash
# Test if callback endpoint is accessible
curl -X GET "https://yourdomain.com/payment/jpesa/test-callback"
```

### **Monitor Callback Status**
```bash
# Check callback status for last 24 hours
php artisan jpesa:monitor-callbacks --hours=24
```

### **Check Callback Logs**
```bash
# View recent callback logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep "JPesa callback"
```

## 🛡️ **Callback Security**

### **Validation Checks**
1. **Transaction Exists**: Verify transaction ID exists in database
2. **Duplicate Prevention**: Check if callback already processed
3. **Amount Verification**: Ensure callback amount matches transaction
4. **Status Mapping**: Map JPesa status to internal status

### **Error Handling**
- **Invalid Transaction**: Log error and return 400
- **Duplicate Callback**: Log warning and return 200
- **Processing Error**: Log error and return 500

## 📊 **Callback Status Tracking**

### **Transaction Status Flow**
```
pending → (callback received) → completed
pending → (callback failed) → failed
pending → (timeout) → failed
```

### **Callback Data Storage**
```json
{
    "jpesa_callback_tid": "JPESA_TRANSACTION_ID",
    "jpesa_callback_memo": "Memo number",
    "jpesa_callback_message": "Status message",
    "jpesa_callback_api_log": "API log reference",
    "jpesa_callback_status": "success|error",
    "callback_received_at": "2025-09-16T19:09:38.812550Z",
    "callback_data": "Full callback data"
}
```

## 🚨 **Troubleshooting**

### **Common Issues**

1. **Callback Not Received**
   - Check if callback URL is publicly accessible
   - Verify HTTPS is working
   - Check firewall settings

2. **Callback Processing Failed**
   - Check transaction exists in database
   - Verify callback data format
   - Check application logs

3. **Duplicate Callbacks**
   - System handles duplicates gracefully
   - Returns success for already processed callbacks

### **Debug Commands**
```bash
# Test callback endpoint
curl -X POST "https://yourdomain.com/payment/jpesa/callback" \
  -d "tx=TEST_123&tid=TEST_TID&api_status=success&msg=Test message"

# Monitor callback status
php artisan jpesa:monitor-callbacks

# Check callback configuration
php artisan tinker --execute="echo config('services.jpesa.callback_url');"
```

## ✅ **Callback Health Check**

### **Verification Steps**
1. ✅ Callback URL is configured
2. ✅ Route is registered and accessible
3. ✅ CSRF protection is disabled
4. ✅ Callback handler processes data correctly
5. ✅ Error handling is implemented
6. ✅ Logging is comprehensive
7. ✅ Duplicate prevention works
8. ✅ Transaction status updates correctly

## 📈 **Performance Monitoring**

### **Key Metrics**
- **Callback Response Time**: Should be < 5 seconds
- **Callback Success Rate**: Should be > 95%
- **Duplicate Callback Rate**: Should be < 5%
- **Failed Transaction Rate**: Should be < 10%

### **Monitoring Commands**
```bash
# Daily callback monitoring
php artisan jpesa:monitor-callbacks --hours=24

# Weekly callback monitoring  
php artisan jpesa:monitor-callbacks --hours=168
```

---

**Your JPesa callback system is now fully configured and monitoring!** 🎉
