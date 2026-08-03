<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0A1628">
    <title>Payment not completed · WifiHyper</title>

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
        <h1 class="pt-head__name">Payment not completed</h1>
        <p class="pt-head__ssid">
            <span class="wh-dot wh-dot--crit"></span> Nothing was charged
        </p>
    </header>

    <main class="pt-card">
        <div class="pt-status">
            <div class="pt-status__icon pt-status__icon--crit">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </div>

            <h2 class="pt-status__title">That didn't go through</h2>
            <p class="pt-status__text">
                The payment was cancelled or timed out, so no money left your account.
                You can try again whenever you're ready.
            </p>

            {{-- The common causes, so the customer can fix it themselves rather
                 than retrying blindly into the same failure. --}}
            <ul class="pt-steps" style="margin-bottom:1.5rem;">
                <li>
                    <span class="pt-steps__n"><i class="fas fa-wallet" aria-hidden="true"></i></span>
                    <span>Not enough balance on your mobile money account</span>
                </li>
                <li>
                    <span class="pt-steps__n"><i class="fas fa-clock" aria-hidden="true"></i></span>
                    <span>The approval prompt expired before it was confirmed</span>
                </li>
                <li>
                    <span class="pt-steps__n"><i class="fas fa-hashtag" aria-hidden="true"></i></span>
                    <span>The PIN was entered incorrectly</span>
                </li>
            </ul>

            @if(request('hotspot'))
                <a href="{{ url('/portal/' . request('hotspot')) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-rotate-right" aria-hidden="true"></i> Try again
                </a>
            @else
                {{-- No hotspot in the query string, so send them back the only way
                     that is guaranteed to work from a captive portal. --}}
                <button type="button" class="btn btn-primary btn-lg" onclick="history.back()">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to packages
                </button>
            @endif

            @if(request('transaction_id'))
                <p class="wh-muted" style="font-size:0.75rem;margin:1.25rem 0 0;">
                    Reference <span class="wh-code">{{ request('transaction_id') }}</span>
                </p>
            @endif
        </div>
    </main>

    <footer class="pt-foot">
        <span class="pt-foot__mark">
            <x-brand.mark :size="14" tone="solid" /> Powered by WifiHyper
        </span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
