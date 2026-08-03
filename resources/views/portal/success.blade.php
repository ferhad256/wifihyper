<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0A1628">
    <title>You're connected · WifiHyper</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}" rel="stylesheet">
    <link href="{{ asset('css/portal.css') }}" rel="stylesheet">
</head>
<body class="pt-body">

<div class="pt-wrap">
    <header class="pt-head">
        <div class="pt-head__brand">
            <x-brand.logo :size="24" tone="light" />
        </div>
        <h1 class="pt-head__name">Payment received</h1>
        <p class="pt-head__ssid">
            <span class="wh-dot wh-dot--ok"></span> Your access is ready
        </p>
    </header>

    <main class="pt-card">
        @if($transaction && $transaction->voucher)
            <div class="pt-status" style="padding-bottom:1rem;">
                <div class="pt-status__icon pt-status__icon--ok">
                    <i class="fas fa-check" aria-hidden="true"></i>
                </div>
                <h2 class="pt-status__title">Here's your WiFi code</h2>
                <p class="pt-status__text" style="margin-bottom:1.25rem;">
                    Enter this on the WiFi sign-in screen. We've also texted it to you.
                </p>

                {{-- The code is the entire reason this page exists, so it gets the
                     largest type on the screen and a one-tap copy. --}}
                <div class="pt-voucher">
                    <p class="pt-voucher__label">Voucher code</p>
                    <p class="pt-voucher__code" id="voucherCode">{{ $transaction->voucher->code }}</p>
                    <button type="button" class="pt-copy" id="copyBtn" onclick="copyVoucherCode()">
                        <i class="fas fa-copy" aria-hidden="true"></i>
                        <span class="pt-copy__label">Copy code</span>
                    </button>
                </div>

                {{-- Copy outcome is announced, not only shown on the button. --}}
                <span class="visually-hidden" role="status" aria-live="polite" id="copyStatus"></span>
            </div>

            @if($transaction->package)
                <div class="pt-receipt">
                    <dl class="pt-receipt__list">
                        <div class="pt-receipt__row">
                            <dt>Package</dt>
                            <dd>{{ $transaction->package->name }}</dd>
                        </div>
                        @if($transaction->package->duration_value && $transaction->package->duration_unit)
                            <div class="pt-receipt__row">
                                <dt>Duration</dt>
                                <dd>{{ $transaction->package->formatted_duration }}</dd>
                            </div>
                        @elseif($transaction->package->duration_hours)
                            <div class="pt-receipt__row">
                                <dt>Duration</dt>
                                <dd>{{ $transaction->package->duration_hours }} hours</dd>
                            </div>
                        @endif
                        @if($transaction->package->data_limit_mb)
                            <div class="pt-receipt__row">
                                <dt>Data</dt>
                                <dd>{{ $transaction->package->data_limit_mb }}MB</dd>
                            </div>
                        @endif
                        <div class="pt-receipt__row">
                            <dt>Paid</dt>
                            <dd class="wh-money">UGX {{ number_format($transaction->amount) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        @else
            <div class="pt-status">
                <div class="pt-status__icon pt-status__icon--ok">
                    <i class="fas fa-check" aria-hidden="true"></i>
                </div>
                <h2 class="pt-status__title">Payment received</h2>
                <p class="pt-status__text">
                    Your voucher code is being sent to your phone by SMS. It usually
                    arrives within a minute.
                </p>
            </div>
        @endif
    </main>

    <footer class="pt-foot">
        <span class="pt-foot__mark">
            <x-brand.mark :size="14" tone="solid" /> Powered by WifiHyper
        </span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function copyVoucherCode() {
        const code = document.getElementById('voucherCode').textContent.trim();
        const button = document.getElementById('copyBtn');

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code)
                .then(() => showCopySuccess(button))
                .catch(() => fallbackCopyTextToClipboard(code, button));
        } else {
            fallbackCopyTextToClipboard(code, button);
        }
    }

    function fallbackCopyTextToClipboard(text, button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy') ? showCopySuccess(button) : showCopyError(button);
        } catch (err) {
            showCopyError(button);
        }

        document.body.removeChild(textArea);
    }

    // Only the label swaps, so the button keeps its size and the layout does
    // not jump under the reader's thumb.
    function setCopyLabel(button, icon, label, done, announce) {
        button.querySelector('i').className = icon;
        button.querySelector('.pt-copy__label').textContent = label;
        button.classList.toggle('is-done', done);
        document.getElementById('copyStatus').textContent = announce;

        setTimeout(function () {
            button.querySelector('i').className = 'fas fa-copy';
            button.querySelector('.pt-copy__label').textContent = 'Copy code';
            button.classList.remove('is-done');
            document.getElementById('copyStatus').textContent = '';
        }, 2000);
    }

    function showCopySuccess(button) {
        setCopyLabel(button, 'fas fa-check', 'Copied', true, 'Voucher code copied.');
    }

    function showCopyError(button) {
        setCopyLabel(button, 'fas fa-xmark', 'Copy failed', false, 'Copy failed. Select the code manually.');
    }
</script>
</body>
</html>
