<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: #667eea;
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
        <h2>Hello {{ $tenant->name }},</h2>
        
        <div class="welcome-box">
            <h3>🎉 Welcome aboard!</h3>
            <p>Thank you for choosing WIFIHYPER for your WiFi hotspot management needs. We're excited to help you streamline your WiFi business operations.</p>
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
        
        <p><strong>Need help getting started?</strong></p>
        <ul>
            <li>Check out our documentation</li>
            <li>Contact our support team</li>
            <li>Join our community forum</li>
        </ul>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $tenant->email }}</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html> 