<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .brand-name {
            color: #2563eb;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .verification-code {
            background-color: #f8fafc;
            border: 2px solid #2563eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            letter-spacing: 4px;
            font-family: 'Courier New', monospace;
        }
        .expiry-info {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .expiry-info strong {
            color: #d97706;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 500;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .security-note {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
        }
        .security-note strong {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="brand-name">WIFIHYPER</div>
            <p>Email Verification Required</p>
        </div>

        <p>Hello <strong>{{ $business_name }}</strong>,</p>

        <p>Thank you for registering with WIFIHYPER! To complete your registration and activate your account, please verify your email address using the verification code below.</p>

        <div class="verification-code">
            <p style="margin: 0 0 15px 0; color: #6b7280;">Your verification code:</p>
            <div class="code">{{ $verification_code }}</div>
        </div>

        <div class="expiry-info">
            <strong>⚠️ Important:</strong> This verification code will expire in <strong>{{ $expires_in }} minutes</strong>.
        </div>

        <p>Please enter this code on the verification page to complete your registration. Once verified, you'll be able to:</p>

        <ul>
            <li>Access your dashboard</li>
            <li>Create and manage WiFi hotspots</li>
            <li>Upload and manage vouchers</li>
            <li>Process payments and transactions</li>
            <li>Access all platform features</li>
        </ul>

        <div class="security-note">
            <strong>🔒 Security Notice:</strong> Never share this verification code with anyone. WIFIHYPER staff will never ask for your verification code.
        </div>

        <p>If you didn't create an account with WIFIHYPER, please ignore this email.</p>

        <p><strong>Need help?</strong> Our support team is here to assist you:</p>
        <ul>
            <li>📧 Email: <a href="mailto:support@wifihyper.com">support@wifihyper.com</a></li>
            <li>💬 WhatsApp: <a href="https://wa.me/256704791624">+256 700 000 000</a></li>
            <li>🌐 Website: <a href="https://wifihyper.com">wifihyper.com</a></li>
        </ul>

        <p>Best regards,<br>
        <strong>The WIFIHYPER Team</strong><br>
        <a href="https://wifihyper.com">wifihyper.com</a></p>

        <div class="footer">
            <p>This email was sent from <strong>WIFIHYPER</strong> - <a href="https://wifihyper.com" style="color: #2563eb;">wifihyper.com</a></p>
            <p>📧 <a href="mailto:support@wifihyper.com" style="color: #2563eb;">support@wifihyper.com</a> | 💬 <a href="https://wa.me/256704791624" style="color: #2563eb;">WhatsApp Support</a></p>
            <p>&copy; {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
        </div>
    </div>
</body>
</html> 