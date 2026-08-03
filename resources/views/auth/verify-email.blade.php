<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - WIFIHYPER</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0A1628;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .container {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 48px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            margin-bottom: 32px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: #0A1628;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo svg {
            width: 40px;
            height: 40px;
            color: white;
        }

        .brand-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: #0A1628;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .brand-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .verification-header {
            margin-bottom: 32px;
        }

        .verification-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
        }

        .verification-icon svg {
            width: 32px;
            height: 32px;
            color: white;
        }

        .verification-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }

        .verification-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .email-highlight {
            color: #0A7A6D;
            font-weight: 600;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            text-align: left;
        }

        .input-container {
            position: relative;
        }

        .verification-input {
            width: 100%;
            padding: 20px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.5em;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            background: #f9fafb;
            color: #111827;
            transition: all 0.3s ease;
            font-family: 'Courier New', monospace;
        }

        .verification-input:focus {
            outline: none;
            border-color: #0A7A6D;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .verification-input::placeholder {
            color: #d1d5db;
            letter-spacing: 0.5em;
        }

        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .submit-btn {
            width: 100%;
            padding: 16px 24px;
            background: #0A1628;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
        }

        .resend-section {
            margin: 24px 0;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .resend-text {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #0A7A6D;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
            transition: color 0.3s ease;
        }

        .resend-btn:hover {
            color: #075E54;
        }

        .countdown-container {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 16px;
            margin: 24px 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.2);
        }

        .countdown-icon {
            width: 20px;
            height: 20px;
            color: #d97706;
        }

        .countdown-text {
            color: #92400e;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .countdown-timer {
            font-weight: 700;
            color: #92400e;
            font-size: 1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #0A7A6D;
            text-decoration: none;
            font-weight: 600;
            margin-top: 24px;
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(-4px);
        }

        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .footer-text {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 8px;
            text-align: left;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .loading {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 640px) {
            .card {
                padding: 32px 24px;
                margin: 16px;
            }
            
            .brand-title {
                font-size: 2rem;
            }
            
            .verification-input {
                font-size: 1.25rem;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="card">
            <!-- Logo and Brand -->
            <div class="logo-container">
                <div class="logo">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                </div>
                <h1 class="brand-title">WIFIHYPER</h1>
                <p class="brand-subtitle">WiFi Management Platform</p>
            </div>

            <!-- Verification Header -->
            <div class="verification-header">
                <div class="verification-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h2 class="verification-title">Verify Your Email</h2>
                <p class="verification-subtitle">
                    We've sent a verification code to <span class="email-highlight">{{ $email }}</span>
                </p>
            </div>

            <!-- Success/Error Messages -->
            @if(session('success'))
                <div class="alert alert-success">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Verification Form -->
            <form id="verificationForm" class="space-y-6" action="{{ route('verification.verify') }}" method="POST">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                
                <div class="form-group">
                    <label for="verification_code" class="form-label">Verification Code</label>
                    <div class="input-container">
                        <input id="verification_code" name="verification_code" type="text" required 
                               class="verification-input"
                               placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                               autocomplete="off">
                        <div class="input-icon">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                    @error('verification_code')
                        <p class="error-message">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <svg class="btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span class="btn-text">Verify Email</span>
                    <div class="loading">
                        <div class="spinner"></div>
                        <span>Verifying...</span>
                    </div>
                </button>
            </form>

            <!-- Resend Section -->
            <div class="resend-section">
                <p class="resend-text">Didn't receive the code?</p>
                <button type="button" onclick="resendCode()" class="resend-btn" id="resendBtn">
                    Resend Code
                </button>
            </div>

            <!-- Countdown Timer -->
            <div class="countdown-container">
                <svg class="countdown-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="countdown-text">
                    Code expires in <span id="countdown" class="countdown-timer">5:00</span>
                </span>
            </div>

            <!-- Back to Login -->
            <a href="{{ route('login') }}" class="back-link">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Login
            </a>

            <!-- Footer -->
            <div class="footer">
                <p class="footer-text">
                    &copy; {{ date('Y') }} WIFIHYPER. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <script>
    let timeLeft = 5 * 60; // 5 minutes in seconds
    let isSubmitting = false;

    function updateCountdown() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        const countdownElement = document.getElementById('countdown');
        
        countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            countdownElement.textContent = 'Expired';
            countdownElement.style.color = '#dc2626';
            document.getElementById('resendBtn').disabled = false;
            return;
        }
        
        timeLeft--;
        setTimeout(updateCountdown, 1000);
    }

    function resendCode() {
        const resendBtn = document.getElementById('resendBtn');
        const countdownContainer = document.querySelector('.countdown-container');
        
        if (confirm('Resend verification code?')) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending...';
            
            fetch('{{ route("verification.resend") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify({
                    email: '{{ $email }}'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage('Verification code resent successfully!', 'success');
                    
                    // Reset countdown
                    timeLeft = 5 * 60;
                    updateCountdown();
                    
                    // Update countdown display
                    countdownContainer.style.background = 'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)';
                    countdownContainer.style.borderColor = '#f59e0b';
                    
                    // Re-enable resend button after 1 minute
                    setTimeout(() => {
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend Code';
                    }, 60000);
                } else {
                    showMessage('Failed to resend code: ' + data.message, 'error');
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Resend Code';
                }
            })
            .catch(error => {
                showMessage('Error resending code: ' + error.message, 'error');
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Code';
            });
        }
    }

    function showMessage(message, type) {
        // Remove existing messages
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create new message
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                ${type === 'success' ? 
                    '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />' :
                    '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />'
                }
            </svg>
            <span>${message}</span>
        `;
        
        // Insert after verification header
        const verificationHeader = document.querySelector('.verification-header');
        verificationHeader.parentNode.insertBefore(alertDiv, verificationHeader.nextSibling);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Form submission handling
    document.getElementById('verificationForm').addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return;
        }
        
        isSubmitting = true;
        const submitBtn = document.getElementById('submitBtn');
        const btnText = submitBtn.querySelector('.btn-text');
        const loading = submitBtn.querySelector('.loading');
        
        btnText.style.display = 'none';
        loading.style.display = 'flex';
        submitBtn.disabled = true;
    });

    // Input formatting
    document.getElementById('verification_code').addEventListener('input', function(e) {
        // Only allow numbers
        this.value = this.value.replace(/[^0-9]/g, '');
        
        // Auto-submit when 6 digits are entered
        if (this.value.length === 6) {
            document.getElementById('verificationForm').submit();
        }
    });

    // Start countdown when page loads
    document.addEventListener('DOMContentLoaded', function() {
        updateCountdown();
        
        // Focus on input
        document.getElementById('verification_code').focus();
    });
    </script>
</body>
</html> 