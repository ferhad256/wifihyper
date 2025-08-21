<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .progress-container {
            margin: 20px 0;
            display: none;
        }
        .progress {
            height: 8px;
            border-radius: 10px;
            background: #e9ecef;
        }
        .progress-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .status-updates {
            margin: 20px 0;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }
        .status-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 10px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        .status-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .status-item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .countdown-display {
            font-size: 18px;
            font-weight: 600;
            color: #667eea;
            margin: 15px 0;
        }
        .auto-refresh-info {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .auto-refresh-info i {
            color: #667eea;
            font-size: 20px;
            margin-right: 8px;
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
                            <li>Your payment is being processed automatically</li>
                            <li>This usually takes 1-2 minutes</li>
                            <li>You'll receive an SMS when complete</li>
                            <li>This page will automatically redirect you</li>
                        </ul>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-container" id="progressContainer">
                        <div class="progress">
                            <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="text-muted mt-2">Processing payment...</small>
                    </div>

                    <!-- Status Updates -->
                    <div class="status-updates" id="statusUpdates">
                        <div class="status-item">
                            <i class="fas fa-clock me-2"></i>
                            <span id="statusText">Initializing payment verification...</span>
                        </div>
                    </div>

                    <!-- Auto-refresh Information -->
                    <div class="auto-refresh-info">
                        <i class="fas fa-sync-alt"></i>
                        <strong>Fully Automated</strong><br>
                        <small>This page automatically checks payment status every 15 seconds</small>
                    </div>
                    
                    <div class="countdown-display">
                        <i class="fas fa-clock me-2"></i>
                        Next check in <span id="countdown">15</span> seconds
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let countdown = 15;
        let checkCount = 0;
        let maxChecks = 40; // Maximum 10 minutes of checking
        const countdownElement = document.getElementById('countdown');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const statusUpdates = document.getElementById('statusUpdates');
        const statusText = document.getElementById('statusText');
        
        // Show progress bar
        progressContainer.style.display = 'block';
        statusUpdates.style.display = 'block';
        
        // Update progress bar
        function updateProgress() {
            const progress = Math.min((checkCount / maxChecks) * 100, 100);
            progressBar.style.width = progress + '%';
        }
        
        // Add status update
        function addStatusUpdate(message, type = 'info') {
            const statusItem = document.createElement('div');
            statusItem.className = `status-item ${type}`;
            statusItem.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>${message}`;
            statusUpdates.appendChild(statusItem);
            statusUpdates.scrollTop = statusUpdates.scrollHeight;
        }
        
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
            checkCount++;
            
            // Update status text
            statusText.innerHTML = `Checking payment status (Attempt ${checkCount})...`;
            
            // Update progress
            updateProgress();
            
            fetch(`/payment/status/${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.status === 'completed') {
                        // Payment completed
                        addStatusUpdate('Payment completed successfully! Redirecting to success page...', 'success');
                        progressBar.style.width = '100%';
                        
                        // Redirect to success page after 2 seconds
                        setTimeout(() => {
                            window.location.href = '/payment/success';
                        }, 2000);
                        
                        clearInterval(autoCheckInterval);
                        return;
                    } else if (data.success && data.status === 'failed') {
                        // Payment failed
                        addStatusUpdate('Payment failed. Please try again.', 'error');
                        clearInterval(autoCheckInterval);
                        
                        // Show retry option
                        setTimeout(() => {
                            if (confirm('Payment failed. Would you like to try again?')) {
                                window.location.reload();
                            }
                        }, 3000);
                        return;
                    } else {
                        // Still pending
                        addStatusUpdate(`Payment still processing... (${data.message || 'Checking with payment gateway'})`);
                        
                        if (checkCount >= maxChecks) {
                            addStatusUpdate('Maximum check attempts reached. Please contact support if payment is still pending.', 'error');
                            clearInterval(autoCheckInterval);
                            return;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                    addStatusUpdate(`Error checking status: ${error.message}`, 'error');
                });
            
            // Reset countdown for next check
            countdown = 15;
        }
        
        // Auto-check status every 15 seconds
        const autoCheckInterval = setInterval(checkStatus, 15000);
        
        // Initial status check
        setTimeout(checkStatus, 2000);
        
        // Update status text periodically
        setInterval(() => {
            if (checkCount > 0) {
                statusText.innerHTML = `Payment verification in progress... (${checkCount} checks completed)`;
            }
        }, 5000);
    </script>
</body>
</html> 