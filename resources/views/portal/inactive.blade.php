<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0A1628">
    <title>{{ $hotspot->name ?? 'Hotspot' }} · Unavailable</title>

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

        <h1 class="pt-head__name">{{ $hotspot->name ?? 'WiFi Hotspot' }}</h1>

        @if($hotspot->ssid ?? null)
            <p class="pt-head__ssid">
                <span class="wh-dot wh-dot--warn"></span>{{ $hotspot->ssid }}
            </p>
        @endif

        @if($hotspot->location ?? null)
            <span class="pt-head__meta">
                <i class="fas fa-location-dot me-1" aria-hidden="true"></i>{{ $hotspot->location }}
            </span>
        @endif
    </header>

    <main class="pt-card">
        <div class="pt-status">
            <div class="pt-status__icon pt-status__icon--warn">
                <i class="fas fa-wifi" aria-hidden="true"></i>
            </div>

            <h2 class="pt-status__title">Not selling right now</h2>
            <p class="pt-status__text">
                This hotspot has been paused, so access can't be bought at the moment.
                It's usually short — try again shortly, or ask at the counter.
            </p>

            <button type="button" class="btn btn-primary btn-lg" onclick="window.location.reload()">
                <i class="fas fa-rotate-right" aria-hidden="true"></i> Check again
            </button>

            <p class="wh-muted" style="font-size:0.75rem;margin:1rem 0 0;">
                Checked {{ now()->format('M d, Y · H:i') }}
            </p>
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
