<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
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
            .voucher-code {
                padding: 15px;
            }
            .voucher-code h3 {
                font-size: 1.5rem;
            }
            .d-flex {
                flex-direction: column;
                align-items: center !important;
            }
            .d-flex .btn {
                margin-top: 10px;
                margin-left: 0 !important;
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
            .voucher-code h3 {
                font-size: 1.3rem;
            }
            .alert {
                font-size: 0.9rem;
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
            .voucher-code {
                padding: 10px;
            }
            .voucher-code h3 {
                font-size: 1.2rem;
            }
            .alert {
                font-size: 0.8rem;
                padding: 10px;
            }
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
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <h3 class="text-success mb-0 me-3" id="voucherCode">{{ $transaction->voucher->code }}</h3>
                                <button class="btn btn-outline-success btn-sm" onclick="copyVoucherCode()" id="copyBtn">
                                    <i class="fas fa-copy me-1"></i>Copy
                                </button>
                            </div>
                            <small class="text-muted">Use this code to connect to WiFi</small>
                        </div>
                        
                        @if($transaction->package)
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-wifi me-2"></i>
                            <strong>Package Details:</strong>
                            <ul class="mb-0 mt-2 text-start">
                                <li><strong>Package:</strong> {{ $transaction->package->name }}</li>
                                @if($transaction->package->duration_value && $transaction->package->duration_unit)
                                    <li><strong>Duration:</strong> {{ $transaction->package->formatted_duration }}</li>
                                @elseif($transaction->package->duration_hours)
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
                            <strong>Payment Successful!</strong>
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
    
    <script>
        function copyVoucherCode() {
            const voucherCode = document.getElementById('voucherCode').textContent;
            const copyBtn = document.getElementById('copyBtn');
            
            // Use the Clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(voucherCode).then(function() {
                    showCopySuccess(copyBtn);
                }).catch(function(err) {
                    fallbackCopyTextToClipboard(voucherCode, copyBtn);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(voucherCode, copyBtn);
            }
        }
        
        function fallbackCopyTextToClipboard(text, button) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            textArea.style.opacity = "0";
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(button);
                } else {
                    showCopyError(button);
                }
            } catch (err) {
                showCopyError(button);
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopySuccess(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
            button.classList.remove('btn-outline-success');
            button.classList.add('btn-success');
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-success');
            }, 2000);
        }
        
        function showCopyError(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-times me-1"></i>Failed';
            button.classList.remove('btn-outline-success');
            button.classList.add('btn-danger');
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-success');
            }, 2000);
        }
    </script>
    
</body>
</html> 