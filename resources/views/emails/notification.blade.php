<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
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
        .notification-box {
            background: white;
            border-left: 4px solid #667eea;
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
    </style>
</head>
<body>
    <div class="header">
        <h1 style="color: #007bff;">WIFIHYPER</h1>
        <p>Notification Center</p>
    </div>
    
    <div class="content">
        <h2>Hello {{ $tenant->name }},</h2>
        
        <div class="notification-box">
            <h3>{{ $notification->title }}</h3>
            <p>{{ $notification->message }}</p>
            <small>Received: {{ $notification->created_at->format('M d, Y H:i') }}</small>
        </div>
        
        <p>You can view all your notifications and manage your account by visiting your dashboard.</p>
        
        <a href="{{ route('dashboard') }}" class="btn">View Dashboard</a>
        
        <div class="notification-box">
            <h3>📞 Need Support?</h3>
            <p>Our support team is here to help you:</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>📧 Email: <a href="mailto:support@wifihyper.com">support@wifihyper.com</a></li>
                <li>💬 WhatsApp: <a href="https://wa.me/256700000000">+256 700 000 000</a></li>
                <li>🌐 Website: <a href="https://wifihyper.com">wifihyper.com</a></li>
            </ul>
        </div>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $tenant->email }} from <strong>WIFIHYPER</strong></p>
        <p>📧 <a href="mailto:support@wifihyper.com">support@wifihyper.com</a> | 💬 <a href="https://wa.me/256700000000">WhatsApp Support</a> | 🌐 <a href="https://wifihyper.com">wifihyper.com</a></p>
        <p>&copy; {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
    </div>
</body>
</html> 