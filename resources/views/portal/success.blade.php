<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - WiFi Portal</title>
    
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .portal-body {
            padding: 30px;
        }
        .success-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .voucher-code {
            background: #f8f9fa;
            border: 2px dashed #28a745;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
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
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Payment Successful!</h3>
                <p class="mb-0">Your WiFi access has been activated</p>
            </div>
            
            <div class="portal-body">
                <div class="text-center">
                    @if($transaction && $transaction->voucher)
                        <h5 class="mb-3">Your WiFi Code</h5>
                        <div class="voucher-code">
                            <h3 class="text-success mb-2" id="voucherCode">{{ $transaction->voucher->code }}</h3>
                            <small class="text-muted">Use this code to connect to WiFi</small>
                        </div>
                        
                        @if($transaction->package)
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-wifi me-2"></i>
                            <strong>Package Details:</strong>
                            <ul class="mb-0 mt-2 text-start">
                                <li><strong>Package:</strong> {{ $transaction->package->name }}</li>
                                @if($transaction->package->duration_hours)
                                    <li><strong>Duration:</strong> {{ $transaction->package->duration_hours }} hours</li>
                                @endif
                                @if($transaction->package->data_limit_mb)
                                    <li><strong>Data Limit:</strong> {{ $transaction->package->data_limit_mb }}MB</li>
                                @endif
                                <li><strong>Amount Paid:</strong> UGX {{ number_format($transaction->amount) }}</li>
                            </ul>
                        </div>
                        @endif
                        
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-sms me-2"></i>
                            <strong>SMS Sent:</strong> Your WiFi voucher code has been sent to {{ $transaction->phone_number ?? 'N/A' }}
                        </div>
                    @else
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Payment Successful!</strong><br>
                            Your WiFi voucher code has been sent to your phone via SMS.
                        </div>
                        
                        
                        <div class="mt-3">
                            <div class="alert alert-warning">
                                <i class="fas fa-sms me-2"></i>
                                <strong>SMS Sent!</strong> The voucher code has also been sent to your phone number: {{ $transaction->phone_number ?? 'N/A' }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html> 