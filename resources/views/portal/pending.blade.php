<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Pending - WiFi Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .portal-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .portal-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .portal-header {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .portal-body {
            padding: 30px;
        }
        .pending-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>What's happening?</strong>
                        <ul class="mb-0 mt-2 text-start">
                            <li>Your payment is being processed</li>
                            <li>This usually takes 1-2 minutes</li>
                            <li>You'll receive an SMS when complete</li>
                            <li>Don't close this page</li>
                        </ul>
                    </div>
                    
                    <div class="mt-4">
                        <button class="btn btn-primary btn-lg" onclick="checkStatus()">
                            <i class="fas fa-sync-alt me-2"></i>Check Status
                        </button>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>Auto-refresh in <span id="countdown">30</span> seconds
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let countdown = 30;
        const countdownElement = document.getElementById('countdown');
        
        // Countdown timer
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                checkStatus();
            }
        }, 1000);
        
        function checkStatus() {
            const transactionId = '{{ $transaction->transaction_id ?? "" }}';
            
            fetch(`/payment/status/${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.status === 'completed') {
                        // Redirect to success page
                        window.location.href = '/payment/success';
                    } else if (data.success && data.status === 'failed') {
                        alert('Payment failed. Please try again.');
                        window.location.reload();
                    } else {
                        // Still pending, reset countdown
                        countdown = 30;
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                    // Reset countdown on error
                    countdown = 30;
                });
        }
        
        // Auto-check status every 30 seconds
        setInterval(checkStatus, 30000);
    </script>
</body>
</html> 