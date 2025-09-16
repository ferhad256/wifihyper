<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing - WIFIHYPER</title>
    <!-- Refresh page every 60 seconds to check for callback completion -->
    <meta http-equiv="refresh" content="60">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .portal-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .portal-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        .portal-header h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .portal-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .portal-body {
            padding: 40px 30px;
        }
        .pending-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        .spinner {
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .alert {
            border: none;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
        }
        .alert-warning {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #ef6c00;
        }
        .alert-success {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            color: #2e7d32;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-card">
            <div class="portal-header">
                <div class="pending-icon">
                    <i class="fas fa-clock spinner"></i>
                </div>
                <h3>Payment Processing</h3>
                <p class="mb-0">Please wait while we confirm your payment</p>
            </div>
            
            <div class="portal-body">
                <div class="text-center">
                    <h5 class="mb-3">Transaction Details</h5>
                    <div class="alert alert-info">
                        <strong>Transaction ID:</strong> {{ $transaction->transaction_id ?? 'N/A' }}<br>
                        <strong>Amount:</strong> UGX {{ number_format($transaction->amount ?? 0) }}<br>
                        <strong>Status:</strong> <span class="badge bg-warning">Pending</span>
                    </div>
                    
                    @if($transaction->package)
                    <div class="alert alert-success">
                        <strong>Package:</strong> {{ $transaction->package->name }}<br>
                        @if($transaction->package->duration_hours)
                            <strong>Duration:</strong> {{ $transaction->package->duration_hours }} hours<br>
                        @endif
                        @if($transaction->package->data_limit_mb)
                            <strong>Data Limit:</strong> {{ $transaction->package->data_limit_mb }}MB<br>
                        @endif
                    </div>
                    @endif
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Please wait...</strong><br>
                        <small>Your payment is being processed. You'll be redirected automatically when complete.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Check if payment was completed via callback on page load
        @if(session('payment_completed'))
            // Payment was completed via callback, redirect to success
            setTimeout(function() {
                window.location.href = '/payment/success';
            }, 1000);
        @endif
        
        // Wait for callback/IPN to complete payment
        // No active status checking - relying on callback system
        
        // Check current transaction status on page load only
        const transactionId = '{{ $transaction->transaction_id ?? "" }}';
        if ('{{ $transaction->status ?? "" }}' === 'completed') {
            // Transaction is already completed, redirect to success
            setTimeout(function() {
                window.location.href = '/payment/success';
            }, 1000);
        }
        
        // Optional: Add a timeout after 15 minutes to show help message
        setTimeout(function() {
            const helpMessage = document.createElement('div');
            helpMessage.className = 'alert alert-warning mt-3';
            helpMessage.innerHTML = `
                <i class="fas fa-info-circle me-2"></i>
                <strong>Taking longer than expected?</strong><br>
                <small>If your payment is taking longer than usual, please contact support with your transaction ID: {{ $transaction->transaction_id ?? 'N/A' }}</small>
            `;
            document.querySelector('.portal-body .text-center').appendChild(helpMessage);
        }, 900000); // 15 minutes
    </script>
</body>
</html> 