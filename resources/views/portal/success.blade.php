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
                padding: 8px;
                align-items: flex-start;
                padding-top: 15px;
            }
            .portal-card {
                max-width: 100%;
                border-radius: 12px;
                margin: 0;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }
            .portal-header {
                padding: 25px 20px !important;
            }
            .portal-header h3 {
                font-size: 1.4rem;
                margin-bottom: 8px;
            }
            .portal-header p {
                font-size: 0.95rem;
            }
            .portal-body {
                padding: 25px 20px !important;
            }
            .btn {
                font-size: 16px;
                padding: 14px 24px;
                min-height: 48px;
                touch-action: manipulation;
            }
            .voucher-code {
                padding: 20px 15px;
                margin: 20px 0;
                border-radius: 12px;
            }
            .voucher-code h3 {
                font-size: 1.8rem;
                font-weight: 700;
                margin-bottom: 15px;
            }
            .voucher-code small {
                font-size: 0.9rem;
            }
            .d-flex {
                flex-direction: column;
                align-items: center !important;
                gap: 15px;
            }
            .d-flex .btn {
                margin: 0;
                width: 100%;
                max-width: 200px;
            }
            .alert {
                border-radius: 12px;
                padding: 18px;
                margin-bottom: 16px;
            }
            .alert ul {
                padding-left: 20px;
            }
            .alert li {
                margin-bottom: 6px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .portal-container {
                padding: 5px;
            }
            .portal-card {
                border-radius: 8px;
            }
            .portal-header {
                padding: 20px 15px !important;
            }
            .portal-header h3 {
                font-size: 1.3rem;
            }
            .portal-header p {
                font-size: 0.9rem;
            }
            .portal-body {
                padding: 20px 15px !important;
            }
            .voucher-code {
                padding: 18px 12px;
                margin: 18px 0;
            }
            .voucher-code h3 {
                font-size: 1.6rem;
                margin-bottom: 12px;
            }
            .voucher-code small {
                font-size: 0.85rem;
            }
            .btn {
                width: 100%;
                margin: 0;
                min-height: 48px;
                font-size: 15px;
            }
            .alert {
                font-size: 0.9rem;
                padding: 15px;
                margin-bottom: 14px;
            }
            .alert li {
                font-size: 0.85rem;
                margin-bottom: 5px;
            }
            .alert strong {
                font-size: 0.9rem;
            }
        }
        
        /* MikroTik Router Compatibility */
        @media screen and (max-width: 320px) {
            .portal-container {
                padding: 3px;
            }
            .portal-card {
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            }
            .portal-header {
                padding: 18px 12px !important;
            }
            .portal-header h3 {
                font-size: 1.2rem;
            }
            .portal-header p {
                font-size: 0.85rem;
            }
            .portal-body {
                padding: 18px 12px !important;
            }
            .voucher-code {
                padding: 15px 10px;
                margin: 15px 0;
            }
            .voucher-code h3 {
                font-size: 1.4rem;
                margin-bottom: 10px;
            }
            .voucher-code small {
                font-size: 0.8rem;
            }
            .btn {
                font-size: 14px;
                padding: 12px 16px;
                min-height: 44px;
            }
            .alert {
                font-size: 0.8rem;
                padding: 12px;
                margin-bottom: 12px;
            }
            .alert li {
                font-size: 0.8rem;
                margin-bottom: 4px;
            }
            .alert strong {
                font-size: 0.85rem;
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
                        
                    @else
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Payment Successful!</strong>
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