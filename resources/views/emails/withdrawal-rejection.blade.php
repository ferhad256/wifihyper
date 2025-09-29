<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Request Rejected</title>
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .alert {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .details {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Withdrawal Request Rejected</h1>
        <p>Your withdrawal request has been reviewed and rejected</p>
    </div>
    
    <div class="content">
        <p>Dear {{ $tenant->name }},</p>
        
        <p>We regret to inform you that your withdrawal request has been rejected by our admin team.</p>
        
        <div class="alert">
            <strong>Request Details:</strong><br>
            <strong>Withdrawal ID:</strong> {{ $withdrawal->withdrawal_id }}<br>
            <strong>Amount Requested:</strong> UGX {{ number_format($withdrawal->amount) }}<br>
            <strong>Request Date:</strong> {{ $withdrawal->created_at->format('M d, Y H:i') }}<br>
            <strong>Current Balance:</strong> UGX {{ number_format($balance) }}
        </div>
        
        @if($admin_notes)
        <div class="details">
            <h3>Admin Notes:</h3>
            <p>{{ $admin_notes }}</p>
        </div>
        @endif
        
        <div class="details">
            <h3>What's Next?</h3>
            <ul>
                <li>Your wallet balance remains unchanged</li>
                <li>You can submit a new withdrawal request if needed</li>
                <li>Please review the admin notes above for guidance</li>
                <li>Contact support if you have any questions</li>
            </ul>
        </div>
        
        <p>If you have any questions about this rejection or need assistance, please don't hesitate to contact our support team.</p>
        
        <a href="{{ $dashboard_url }}" class="button">View Dashboard</a>
        
        <p>Thank you for using WIFIHYPER.</p>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} WIFIHYPER. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
