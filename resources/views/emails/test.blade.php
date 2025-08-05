<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test</title>
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
        .test-box {
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
        .success {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Email Configuration Test</h1>
        <p style="color: #007bff; font-weight: bold;">WIFIHYPER</p>
    </div>
    
    <div class="content">
        <h2>Hello {{ $tenant->name }},</h2>
        
        <div class="test-box">
            <h3 class="success">✅ Email Configuration Successful!</h3>
            <p>This is a test email to verify that your email configuration is working correctly.</p>
            
            <p><strong>Test Details:</strong></p>
            <ul>
                <li><strong>Recipient:</strong> {{ $tenant->email }}</li>
                <li><strong>Test Time:</strong> {{ $test_time }}</li>
                <li><strong>Mail Driver:</strong> {{ config('mail.default') }}</li>
                <li><strong>From Address:</strong> {{ config('mail.from.address') }}</li>
            </ul>
        </div>
        
        <p>If you received this email, it means:</p>
        <ul>
            <li>✅ Your Resend API key is configured correctly</li>
            <li>✅ Your email templates are working</li>
            <li>✅ Your mail configuration is properly set up</li>
            <li>✅ You can now receive email notifications</li>
        </ul>
        
        <p>You can now enable email notifications in your settings to receive important updates via email.</p>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $tenant->email }}</p>
        <p>&copy; {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
    </div>
</body>
</html> 