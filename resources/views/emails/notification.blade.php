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
        .notification-box {
            background: white;
            border-left: 4px solid #0A7A6D;
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
                <li>📧 Email: <a href="mailto:wifihyper01@gmail.com">wifihyper01@gmail.com</a></li>
                <li>💬 WhatsApp: <a href="https://wa.me/256792746413">0792746413</a></li>
                <li>🌐 Website: <a href="https://wifihyper.com">wifihyper.com</a></li>
            </ul>
        </div>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $tenant->email }} from <strong>WIFIHYPER</strong></p>
        <p>📧 <a href="mailto:wifihyper01@gmail.com">wifihyper01@gmail.com</a> | 💬 <a href="https://wa.me/256792746413">WhatsApp Support</a> | 🌐 <a href="https://wifihyper.com">wifihyper.com</a></p>
        <p>&copy; {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
    </div>
</body>
</html> 