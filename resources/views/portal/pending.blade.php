<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Payment Processing - WIFIHYPER</title>
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
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .portal-container {
                padding: 10px;
                align-items: flex-start;
                padding-top: 20px;
            }
            .portal-card {
                max-width: 100%;
                border-radius: 10px;
                margin: 0;
            }
            .portal-header {
                padding: 20px 15px !important;
            }
            .portal-body {
                padding: 20px 15px !important;
            }
            .btn {
                font-size: 16px;
                padding: 12px 20px;
            }
            .spinner-border {
                width: 2rem;
                height: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .portal-container {
                padding: 5px;
            }
            .portal-card {
                border-radius: 5px;
            }
            .portal-header h3 {
                font-size: 1.3rem;
            }
            .portal-header p {
                font-size: 0.9rem;
            }
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            .spinner-border {
                width: 1.5rem;
                height: 1.5rem;
            }
        }
        
        /* MikroTik Router Compatibility */
        @media screen and (max-width: 320px) {
            .portal-container {
                padding: 2px;
            }
            .portal-card {
                border-radius: 3px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            .portal-header {
                padding: 15px 10px !important;
            }
            .portal-body {
                padding: 15px 10px !important;
            }
            .btn {
                font-size: 14px;
                padding: 10px 15px;
            }
            .spinner-border {
                width: 1.2rem;
                height: 1.2rem;
            }
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
                        @if($transaction->package->duration_value && $transaction->package->duration_unit)
                            <strong>Duration:</strong> {{ $transaction->package->formatted_duration }}<br>
                        @elseif($transaction->package->duration_hours)
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
        const transactionId = '{{ $transaction->transaction_id ?? "" }}';
        let statusCheckInterval;
        let checkCount = 0;
        const maxChecks = 300; // 5 minutes of checking (every 1 second)
        
        // Check if payment was completed via callback on page load
        @if(session('payment_completed'))
            // Payment was completed via callback, redirect to success
            setTimeout(function() {
                window.location.href = '/payment/success';
            }, 1000);
        @endif
        
        // Check current transaction status on page load
        if ('{{ $transaction->status ?? "" }}' === 'completed') {
            // Transaction is already completed, redirect to success
            setTimeout(function() {
                window.location.href = '/payment/success';
            }, 1000);
        } else {
            // Start real-time status checking
            startStatusChecking();
        }
        
        function startStatusChecking() {
            console.log('Starting real-time status checking for transaction:', transactionId);
            
            // Check status every 1 second
            statusCheckInterval = setInterval(function() {
                checkTransactionStatus();
            }, 1000);
            
            // Stop checking after 5 minutes
            setTimeout(function() {
                if (statusCheckInterval) {
                    clearInterval(statusCheckInterval);
                    console.log('Status checking stopped after 5 minutes');
                    showTimeoutMessage();
                }
            }, 300000); // 5 minutes
        }
        
        function checkTransactionStatus() {
            if (checkCount >= maxChecks) {
                clearInterval(statusCheckInterval);
                showTimeoutMessage();
                return;
            }
            
            checkCount++;
            
            fetch('/payment/check-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    transaction_id: transactionId
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Status check result:', data);
                
                if (data.status === 'completed') {
                    // Payment completed! Redirect to success page
                    clearInterval(statusCheckInterval);
                    console.log('Payment completed! Redirecting to success page...');
                    
                    // Update UI to show success
                    updateUIForSuccess(data);
                    
                    // Redirect after a short delay
                    setTimeout(function() {
                        window.location.href = '/payment/success';
                    }, 2000);
                } else if (data.status === 'failed') {
                    // Payment failed! Redirect to failed page
                    clearInterval(statusCheckInterval);
                    console.log('Payment failed! Redirecting to failed page...');
                    
                    // Update UI to show failure
                    updateUIForFailure(data);
                    
                    // Redirect after a short delay
                    setTimeout(function() {
                        window.location.href = '/payment/failed';
                    }, 3000);
                }
                // If status is still 'pending', continue checking
            })
            .catch(error => {
                console.error('Error checking transaction status:', error);
                // Continue checking even if there's an error
            });
        }
        
        function updateUIForSuccess(data) {
            // Update the status badge
            const statusBadge = document.querySelector('.badge');
            if (statusBadge) {
                statusBadge.textContent = 'Completed';
                statusBadge.className = 'badge bg-success';
            }
            
            // Update the header
            const header = document.querySelector('.portal-header h3');
            if (header) {
                header.textContent = 'Payment Successful!';
            }
            
            const subHeader = document.querySelector('.portal-header p');
            if (subHeader) {
                subHeader.textContent = 'Your payment has been confirmed';
            }
            
            // Update the icon
            const icon = document.querySelector('.pending-icon i');
            if (icon) {
                icon.className = 'fas fa-check-circle';
                icon.style.color = '#28a745';
            }
            
            // Update the warning message
            const warningAlert = document.querySelector('.alert-warning');
            if (warningAlert) {
                warningAlert.className = 'alert alert-success';
                warningAlert.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Payment Confirmed!</strong><br>
                    <small>Your voucher code has been sent via SMS. Redirecting to success page...</small>
                `;
            }
        }
        
        function updateUIForFailure(data) {
            // Update the status badge
            const statusBadge = document.querySelector('.badge');
            if (statusBadge) {
                statusBadge.textContent = 'Failed';
                statusBadge.className = 'badge bg-danger';
            }
            
            // Update the header
            const header = document.querySelector('.portal-header h3');
            if (header) {
                header.textContent = 'Payment Failed';
            }
            
            const subHeader = document.querySelector('.portal-header p');
            if (subHeader) {
                subHeader.textContent = 'Your payment could not be processed';
            }
            
            // Update the icon
            const icon = document.querySelector('.pending-icon i');
            if (icon) {
                icon.className = 'fas fa-times-circle';
                icon.style.color = '#dc3545';
            }
            
            // Update the warning message
            const warningAlert = document.querySelector('.alert-warning');
            if (warningAlert) {
                warningAlert.className = 'alert alert-danger';
                warningAlert.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Payment Failed!</strong><br>
                    <small>Your payment could not be processed. Redirecting to failed page...</small>
                `;
            }
        }
        
        function showTimeoutMessage() {
            const helpMessage = document.createElement('div');
            helpMessage.className = 'alert alert-warning mt-3';
            helpMessage.innerHTML = `
                <i class="fas fa-info-circle me-2"></i>
                <strong>Taking longer than expected?</strong><br>
                <small>If your payment is taking longer than usual, please contact support with your transaction ID: ${transactionId}</small>
            `;
            document.querySelector('.portal-body .text-center').appendChild(helpMessage);
        }
    </script>
</body>
</html> 