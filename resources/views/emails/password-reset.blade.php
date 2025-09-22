<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .content {
            padding: 40px 30px;
        }
        .content h2 {
            color: #333;
            margin-top: 0;
            font-size: 24px;
        }
        .content p {
            margin-bottom: 20px;
            font-size: 16px;
        }
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
            text-align: center;
        }
        .reset-button:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
            text-decoration: none;
        }
        .security-note {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }
        .security-note h4 {
            margin-top: 0;
            color: #667eea;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .expires-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>WIFIHYPER</h1>
            <p>Password Reset Request</p>
        </div>
        
        <div class="content">
            <h2>Hello {{ $tenant->name }},</h2>
            
            <p>We received a request to reset your password for your WIFIHYPER account. If you made this request, click the button below to reset your password:</p>
            
            <div style="text-align: center;">
                <a href="{{ $reset_url }}" class="reset-button">Reset My Password</a>
            </div>
            
            <div class="expires-info">
                <strong>⏰ Important:</strong> This password reset link will expire in {{ $expires_in }} minutes for security reasons.
            </div>
            
            <div class="security-note">
                <h4>🔒 Security Information</h4>
                <ul>
                    <li>This link can only be used once</li>
                    <li>The link will expire in {{ $expires_in }} minutes</li>
                    <li>If you didn't request this reset, please ignore this email</li>
                    <li>Your password will remain unchanged until you click the link above</li>
                </ul>
            </div>
            
            <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;">
                {{ $reset_url }}
            </p>
            
            <p>If you have any questions or need assistance, please contact our support team.</p>
            
            <p>Best regards,<br>
            The WIFIHYPER Team</p>
        </div>
        
        <div class="footer">
            <p>This email was sent to {{ $tenant->email }} because a password reset was requested for your WIFIHYPER account.</p>
            <p>© {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
            <p><a href="{{ route('landing') }}">Visit our website</a> | <a href="{{ route('login') }}">Login to your account</a></p>
        </div>
    </div>
</body>
</html>
