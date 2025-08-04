# WiFi SaaS - Multi-Tenant WiFi Management Platform

A comprehensive SaaS platform for managing multiple WiFi hotspots, voucher systems, and payment processing. Perfect for cafes, hotels, and businesses.

## 🚀 Features

- **Multi-Tenant Architecture**: Each tenant has their own isolated data
- **Voucher Management**: Upload and manage voucher codes in bulk
- **Payment Integration**: Yo Payments integration for mobile money
- **SMS Integration**: UG SMS gateway for voucher delivery
- **Analytics Dashboard**: Real-time sales and usage analytics
- **Captive Portal**: Customizable WiFi login pages
- **Responsive Design**: Works on all devices

## 📋 Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer
- XAMPP (for local development)

## 🛠️ Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd wifi-saas
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Setup
Copy the `.env.example` file to `.env`:
```bash
cp .env.example .env
```

Update the `.env` file with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wifi_saas
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Run Database Migrations
```bash
php artisan migrate
```

### 6. Set Permissions (Linux/Mac)
```bash
chmod -R 775 storage bootstrap/cache
```

### 7. Start the Application
```bash
php artisan serve
```

## 🌐 Access the Application

- **Landing Page**: http://localhost:8000
- **Admin Login**: http://localhost:8000/login
- **Registration**: http://localhost:8000/register

## 🔧 Configuration

### Payment Gateway (Yo Payments)
Update your `.env` file with Yo Payments credentials:
```env
YO_PAYMENTS_BASE_URL=https://pay.yo.co.ug/api
YO_PAYMENTS_API_KEY=your_api_key_here
YO_PAYMENTS_SECRET_KEY=your_secret_key_here
YO_PAYMENTS_MERCHANT_ID=your_merchant_id_here
YO_PAYMENTS_ENVIRONMENT=sandbox
```

### SMS Gateway (UG SMS)
Update your `.env` file with UG SMS credentials:
```env
UG_SMS_BASE_URL=https://api.ug-sms.com/api/v1
UG_SMS_API_KEY=your_api_key_here
UG_SMS_USERNAME=your_username_here
UG_SMS_SENDER_ID=WiFiSaaS
UG_SMS_ENVIRONMENT=sandbox
```

## 📱 Usage

### For Tenants (WiFi Business Owners)

1. **Register**: Create an account at the landing page
2. **Add Hotspots**: Configure your WiFi hotspots
3. **Create Packages**: Set up WiFi packages with pricing
4. **Upload Vouchers**: Add voucher codes for your packages
5. **Monitor Sales**: Track transactions and analytics

### For End Users (WiFi Customers)

1. **Connect to WiFi**: Connect to the hotspot
2. **Access Portal**: Automatically redirected to payment page
3. **Select Package**: Choose WiFi package and duration
4. **Make Payment**: Pay via mobile money
5. **Receive Voucher**: Get voucher code via SMS
6. **Connect**: Use voucher to access WiFi

## 🗄️ Database Structure

### Core Tables
- `tenants` - Tenant information and settings
- `hotspots` - WiFi hotspot configurations
- `packages` - WiFi packages and pricing
- `vouchers` - Voucher codes and status
- `transactions` - Payment transactions
- `sms_logs` - SMS delivery logs

## 🔒 Security Features

- Session-based authentication
- CSRF protection
- Input validation and sanitization
- SQL injection prevention
- XSS protection

## 🚀 Deployment

### For Production

1. **Update Environment**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Configure production database

2. **Optimize Application**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Set Permissions**
   ```bash
   chmod -R 755 storage bootstrap/cache
   ```

4. **Configure Web Server**
   - Point document root to `public/` directory
   - Enable URL rewriting
   - Configure SSL certificate

### For XAMPP

1. Copy the project to `htdocs/`
2. Access via `http://localhost/wifi-saas/public`
3. Run the setup script: `setup.bat`

## 📊 API Endpoints

### Authentication
- `POST /login` - Tenant login
- `POST /register` - Tenant registration
- `POST /logout` - Tenant logout

### Dashboard
- `GET /dashboard` - Main dashboard
- `GET /billing` - Transaction history
- `GET /hotspots` - Hotspot management
- `GET /vouchers` - Voucher management

### Payment
- `POST /payment/process` - Process payment
- `GET /payment/callback` - Payment callback
- `GET /payment/status/{id}` - Check payment status

### Portal
- `GET /portal/{hotspot}` - Captive portal
- `GET /portal/{hotspot}/payment` - Payment form

## 🧪 Testing

### Default Credentials
- **Email**: admin@example.com
- **Password**: password123

### Test Data
You can create test data using Laravel seeders:
```bash
php artisan db:seed
```

## 📞 Support

For support and questions:
- Email: support@wifi-saas.com
- Documentation: https://docs.wifi-saas.com
- Issues: GitHub Issues

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📈 Roadmap

- [ ] Mobile app for tenants
- [ ] Advanced analytics
- [ ] Multi-language support
- [ ] API documentation
- [ ] White-label solution
- [ ] Advanced reporting
- [ ] Bulk SMS features
- [ ] Integration with more payment gateways

---

**WiFi SaaS** - Making WiFi management simple and profitable! 🚀
