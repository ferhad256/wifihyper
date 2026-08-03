<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0A1628">
    <title>Confirming payment · WifiHyper</title>

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
        <h1 class="pt-head__name" id="headTitle">Approve on your phone</h1>
        <p class="pt-head__ssid" id="headSub">
            <span class="wh-dot wh-dot--live"></span> Waiting for confirmation
        </p>
    </header>

    <main class="pt-card">
        <div class="pt-status">
            <div class="pt-status__icon pt-status__icon--wait" id="statusIcon">
                <i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
            </div>

            <h2 class="pt-status__title" id="statusTitle">Check your phone</h2>
            <p class="pt-status__text" id="statusText">
                We've sent a payment request to your number. Enter your mobile money PIN
                to approve it — this page updates on its own.
            </p>

            <div class="pt-progress" id="progressBar">
                <div class="pt-progress__bar"></div>
            </div>

            {{-- aria-live so the outcome is announced, not only shown. --}}
            <div id="statusRegion" role="status" aria-live="polite"></div>

            <ol class="pt-steps" id="stepList">
                <li class="is-active">
                    <span class="pt-steps__n">1</span>
                    <span>Approve the prompt on your phone</span>
                </li>
                <li>
                    <span class="pt-steps__n">2</span>
                    <span>We confirm the payment</span>
                </li>
                <li>
                    <span class="pt-steps__n">3</span>
                    <span>Your WiFi code arrives by SMS</span>
                </li>
            </ol>
        </div>

        <div class="pt-receipt">
            <dl class="pt-receipt__list">
                <div class="pt-receipt__row">
                    <dt>Reference</dt>
                    <dd><span class="wh-code">{{ $transaction->transaction_id ?? 'N/A' }}</span></dd>
                </div>
                <div class="pt-receipt__row">
                    <dt>Amount</dt>
                    <dd class="wh-money">UGX {{ number_format($transaction->amount ?? 0) }}</dd>
                </div>
                @if($transaction->package)
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
                @endif
            </dl>
        </div>
    </main>

    <footer class="pt-foot">
        <span class="pt-foot__mark">
            <x-brand.mark :size="14" tone="solid" /> Powered by WifiHyper
        </span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const transactionId = '{{ $transaction->transaction_id ?? "" }}';
    let statusCheckInterval;
    let checkCount = 0;
    const maxChecks = 300; // 5 minutes at one check per second

    // Addressed by id rather than by layout class, so restyling this page can
    // no longer silently break the payment outcome messaging.
    const el = {
        headTitle: document.getElementById('headTitle'),
        headSub:   document.getElementById('headSub'),
        icon:      document.getElementById('statusIcon'),
        title:     document.getElementById('statusTitle'),
        text:      document.getElementById('statusText'),
        progress:  document.getElementById('progressBar'),
        region:    document.getElementById('statusRegion'),
        steps:     document.getElementById('stepList')
    };

    @if(session('payment_completed'))
        setTimeout(function () { window.location.href = '/payment/success'; }, 1000);
    @endif

    if ('{{ $transaction->status ?? "" }}' === 'completed') {
        setTimeout(function () { window.location.href = '/payment/success'; }, 1000);
    } else {
        startStatusChecking();
    }

    function startStatusChecking() {
        statusCheckInterval = setInterval(checkTransactionStatus, 1000);

        setTimeout(function () {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                showTimeoutMessage();
            }
        }, 300000);
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
            body: JSON.stringify({ transaction_id: transactionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'completed') {
                clearInterval(statusCheckInterval);
                updateUIForSuccess();
                setTimeout(function () {
                    window.location.href = '/payment/success?transaction_id=' + encodeURIComponent(transactionId);
                }, 2000);
            } else if (data.status === 'failed') {
                clearInterval(statusCheckInterval);
                updateUIForFailure();
                setTimeout(function () { window.location.href = '/payment/failed'; }, 3000);
            }
            // Still pending — keep polling.
        })
        .catch(error => console.error('Error checking transaction status:', error));
    }

    function updateUIForSuccess() {
        el.headTitle.textContent = 'Payment received';
        el.headSub.innerHTML = '<span class="wh-dot wh-dot--ok"></span> Confirmed';
        el.icon.className = 'pt-status__icon pt-status__icon--ok';
        el.icon.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
        el.title.textContent = 'Payment confirmed';
        el.text.textContent = 'Your voucher code is on its way by SMS. Taking you to it now…';
        el.progress.style.display = 'none';
        el.steps.style.display = 'none';
        el.region.innerHTML = '<div class="alert alert-success mb-0">Payment confirmed.</div>';
    }

    function updateUIForFailure() {
        el.headTitle.textContent = 'Payment not completed';
        el.headSub.innerHTML = '<span class="wh-dot wh-dot--crit"></span> Failed';
        el.icon.className = 'pt-status__icon pt-status__icon--crit';
        el.icon.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
        el.title.textContent = 'That payment did not go through';
        el.text.textContent = 'Nothing was charged. You can try again in a moment.';
        el.progress.style.display = 'none';
        el.steps.style.display = 'none';
        el.region.innerHTML = '<div class="alert alert-danger mb-0">Payment failed.</div>';
    }

    function showTimeoutMessage() {
        el.progress.style.display = 'none';
        el.icon.className = 'pt-status__icon pt-status__icon--warn';
        el.icon.innerHTML = '<i class="fas fa-hourglass-half" aria-hidden="true"></i>';
        el.title.textContent = 'This is taking longer than usual';
        el.text.textContent = 'If money left your account your code will still arrive by SMS. Quote the reference below if you need help.';
        el.region.innerHTML =
            '<div class="alert alert-warning mb-0">Still unconfirmed after 5 minutes. Reference <strong>'
            + transactionId + '</strong>.</div>';
    }
</script>
</body>
</html>
