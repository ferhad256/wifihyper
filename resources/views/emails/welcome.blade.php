<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Welcome to WIFIHYPER</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #0A1628;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 0 0 8px 8px;
        }
        .welcome-box {
            background: white;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0A7A6D;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        .feature-list {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .feature-list ul {
            margin: 0;
            padding-left: 20px;
        }
        .feature-list li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="color: #007bff;">Welcome to WIFIHYPER</h1>
        <p>Your WiFi Management Platform</p>
    </div>
    
    <div class="content">
        <h2>Hello {{ $business_name }},</h2>
        
        <div class="welcome-box">
            <h3>🎉 Welcome aboard!</h3>
            <p>Thank you for choosing WIFIHYPER for your WiFi hotspot management needs. We're excited to help you streamline your WiFi business operations.</p>
            <p><strong>Your account has been successfully verified and activated!</strong></p>
        </div>
        
        <div class="feature-list">
            <h3>What you can do with WIFIHYPER:</h3>
            <ul>
                <li>📊 Manage multiple WiFi hotspots from one dashboard</li>
                <li>🎫 Upload and manage voucher codes in bulk</li>
                <li>💰 Process payments and track transactions</li>
                <li>📱 Send SMS notifications to customers</li>
                <li>📈 View detailed analytics and reports</li>
                <li>⚙️ Customize settings and notifications</li>
            </ul>
        </div>
        
        <p>To get started, please log in to your dashboard and set up your first hotspot.</p>
        
        <a href="{{ $login_url }}" class="btn">Login to Dashboard</a>
        
        <div class="feature-list">
            <h3><strong>Need help getting started?</strong></h3>
            <p>Our support team is ready to help you succeed:</p>
            <ul>
                <li>📧 Email Support: <a href="mailto:wifihyper01@gmail.com">wifihyper01@gmail.com</a></li>
                <li>💬 WhatsApp Support: <a href="https://wa.me/256792746413">0792746413</a></li>
                <li>📖 Documentation: <a href="https://wifihyper.com/docs">wifihyper.com/docs</a></li>
                <li>🌐 Visit our website: <a href="https://wifihyper.com">wifihyper.com</a></li>
            </ul>
        </div>

        <p style="text-align: center; margin-top: 30px;">
            <strong>Welcome to the WIFIHYPER family!</strong><br>
            <a href="https://wifihyper.com">wifihyper.com</a>
        </p>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $email }} from <strong>WIFIHYPER</strong></p>
        <p>📧 <a href="mailto:wifihyper01@gmail.com">wifihyper01@gmail.com</a> | 💬 <a href="https://wa.me/256792746413">WhatsApp Support</a> | 🌐 <a href="https://wifihyper.com">wifihyper.com</a></p>
        <p>&copy; {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
    </div>
</body>
</html> 