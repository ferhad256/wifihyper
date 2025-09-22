<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Approved</title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        .success-box {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .success-box h3 {
            color: #155724;
            margin-top: 0;
            font-size: 20px;
        }
        .amount-display {
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }
        .balance-info {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }
        .balance-info h4 {
            margin-top: 0;
            color: #007bff;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        .dashboard-button {
            display: inline-block;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
            text-align: center;
        }
        .dashboard-button:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            color: white;
            text-decoration: none;
        }
        .transaction-details {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .transaction-details h4 {
            margin-top: 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>WIFIHYPER</h1>
            <p>Withdrawal Approved</p>
        </div>
        
        <div class="content">
            <h2>Hello {{ $tenant->name }},</h2>
            
            <div class="success-box">
                <h3>🎉 Withdrawal Approved!</h3>
                <p>Your withdrawal request has been successfully approved and processed.</p>
                <div class="amount-display">UGX {{ number_format($amount) }}</div>
                <p><strong>Status:</strong> Completed</p>
            </div>
            
            <div class="balance-info">
                <h4>💰 Current Wallet Balance</h4>
                <p>Your current wallet balance is: <strong>UGX {{ number_format($balance) }}</strong></p>
            </div>
            
            <div class="transaction-details">
                <h4>📋 Transaction Details</h4>
                <p><strong>Withdrawal ID:</strong> #{{ $withdrawal->id }}</p>
                <p><strong>Amount Withdrawn:</strong> UGX {{ number_format($withdrawal->amount) }}</p>
                <p><strong>Approved At:</strong> {{ $withdrawal->approved_at->format('M d, Y H:i') }}</p>
                <p><strong>Approved By:</strong> {{ $withdrawal->admin->name ?? 'Admin' }}</p>
                @if($withdrawal->admin_notes)
                    <p><strong>Admin Notes:</strong> {{ $withdrawal->admin_notes }}</p>
                @endif
            </div>
            
            <p>Your withdrawal has been processed and the funds have been deducted from your wallet balance. You can now use these funds as needed.</p>
            
            <div style="text-align: center;">
                <a href="{{ $dashboard_url }}" class="dashboard-button">View Dashboard</a>
            </div>
            
            <p>If you have any questions about this withdrawal or need assistance, please don't hesitate to contact our support team.</p>
            
            <p>Best regards,<br>
            The WIFIHYPER Team</p>
        </div>
        
        <div class="footer">
            <p>This email was sent to {{ $tenant->email }} regarding your withdrawal request.</p>
            <p>© {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
            <p><a href="{{ route('landing') }}">Visit our website</a> | <a href="{{ route('login') }}">Login to your account</a></p>
        </div>
    </div>
</body>
</html>
