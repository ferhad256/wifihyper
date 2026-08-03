<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {{-- Zoom is deliberately NOT disabled here. The previous viewport used
         maximum-scale=1, user-scalable=no, which blocks pinch-zoom — a WCAG
         failure on a screen where people read a code and type a phone number. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0A1628">
    <title>{{ $hotspot->name }} · WiFi</title>

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

        <h1 class="pt-head__name">{{ $hotspot->name }}</h1>

        @if($hotspot->ssid)
            <p class="pt-head__ssid">
                <i class="fas fa-wifi" aria-hidden="true"></i>{{ $hotspot->ssid }}
            </p>
        @endif

        @if($hotspot->location)
            <span class="pt-head__meta">
                <i class="fas fa-location-dot me-1" aria-hidden="true"></i>{{ $hotspot->location }}
            </span>
        @endif
    </header>

    <main class="pt-card">
        @if($hotspot->is_active)
            @if($packages->count() > 0)
                <div class="pt-card__head">
                    <h2 class="pt-card__title">Choose how long you need</h2>
                    <p class="pt-card__sub">Pay with mobile money — your code arrives by SMS.</p>
                </div>

                <div class="pt-card__body packages-container">
                    @foreach($packages as $package)
                        {{-- The whole row is the control. Name and price are carried as
                             data attributes so the availability poller never has to read
                             them back out of the visible markup. --}}
                        <button
                            type="button"
                            class="pt-pkg"
                            data-package-id="{{ $package->id }}"
                            data-package-name="{{ $package->name }}"
                            data-package-price="{{ $package->price }}"
                            @if(!$package->has_vouchers) disabled @endif
                            onclick="openPaymentModal({{ $package->id }}, @js($package->name), {{ $package->price }})"
                        >
                            <span class="pt-pkg__main">
                                <span class="pt-pkg__name">{{ $package->name }}</span>

                                @if($package->description)
                                    <span class="pt-pkg__desc">{{ $package->description }}</span>
                                @endif

                                <span class="pt-pkg__facts">
                                    @if($package->duration_value && $package->duration_unit)
                                        <span><i class="fas fa-clock" aria-hidden="true"></i>{{ $package->formatted_duration }}</span>
                                    @elseif($package->duration_hours)
                                        <span><i class="fas fa-clock" aria-hidden="true"></i>{{ $package->duration_hours }} hours</span>
                                    @endif

                                    @if($package->data_limit_mb)
                                        <span><i class="fas fa-database" aria-hidden="true"></i>{{ $package->data_limit_mb }}MB</span>
                                    @endif

                                    <span class="stock-status">
                                        @if($package->has_vouchers)
                                            <span class="wh-pill wh-pill--ok">Available</span>
                                        @else
                                            <span class="wh-pill wh-pill--crit">Sold out</span>
                                        @endif
                                    </span>
                                </span>
                            </span>

                            <span class="pt-pkg__side">
                                <span class="pt-pkg__price">
                                    <small>UGX</small> {{ number_format($package->price) }}
                                </span>
                                <span class="pt-pkg__go">
                                    @if($package->has_vouchers)
                                        Buy <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                    @else
                                        Unavailable
                                    @endif
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="pt-status">
                    <div class="pt-status__icon pt-status__icon--warn">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <h2 class="pt-status__title">No packages yet</h2>
                    <p class="pt-status__text">
                        This hotspot hasn't set up any packages. Please ask at the counter.
                    </p>
                </div>
            @endif
        @else
            <div class="pt-status">
                <div class="pt-status__icon pt-status__icon--warn">
                    <i class="fas fa-wifi" aria-hidden="true"></i>
                </div>
                <h2 class="pt-status__title">This hotspot is offline</h2>
                <p class="pt-status__text">
                    It isn't selling access right now. Please ask at the counter.
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

<!-- ============ Payment sheet ============
     Deliberately plain HTML/CSS/JS rather than the Bootstrap modal component —
     an earlier change moved it off Bootstrap for mobile reliability, so that
     decision and the .close / .btn-cancel hooks are preserved here. -->
<div id="paymentModal" class="pt-modal" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
    <div class="pt-modal__panel" role="document">
        <div class="pt-modal__head">
            <h5 id="paymentModalTitle">Confirm your purchase</h5>
            <button type="button" class="close" aria-label="Close">&times;</button>
        </div>

        <form id="paymentForm" method="POST" action="{{ route('payment.initiate') }}">
            @csrf
            <input type="hidden" name="hotspot_id" value="{{ $hotspot->id }}">
            <input type="hidden" name="package_id" id="modal_package_id">

            <div class="pt-modal__body">
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                @endif

                <div class="pt-summary">
                    <span class="package-name" id="modal_package_name"></span>
                    <span class="package-price" id="modal_package_price"></span>
                </div>

                <div class="pt-field">
                    <label for="modal_phone_number">Mobile money number</label>
                    <input
                        type="tel"
                        id="modal_phone_number"
                        name="phone_number"
                        placeholder="07XX XXX XXX"
                        value="{{ old('phone_number') }}"
                        inputmode="numeric"
                        autocomplete="tel"
                        maxlength="10"
                        required
                        aria-describedby="phoneHint"
                    >
                    <span class="pt-field__hint" id="phoneHint">
                        You'll get a prompt on this phone to approve the payment.
                    </span>
                    {{-- Replaces the old alert() calls: the message appears beside the
                         field it refers to and is announced to screen readers. --}}
                    <span class="pt-field__error" id="phoneError" role="alert"></span>
                </div>
            </div>

            <div class="pt-modal__foot">
                <button type="button" class="btn btn-secondary btn-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary" id="processPaymentBtn">
                    <span class="btn-text">Pay now</span>
                    <span class="btn-loading" style="display: none;">Processing…</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let availabilityCheckInterval;
    let lastFocusedTrigger = null;

    const modalEl = document.getElementById('paymentModal');
    const phoneInput = document.getElementById('modal_phone_number');
    const phoneError = document.getElementById('phoneError');

    // ---- Modal ------------------------------------------------------------
    function showModal(modalId) {
        document.getElementById(modalId).classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function hideModal(modalId) {
        document.getElementById(modalId).classList.remove('is-open');
        document.body.style.overflow = '';
        clearPhoneError();
        // Send focus back to the package the customer opened, so keyboard and
        // screen-reader users don't get dropped at the top of the page.
        if (lastFocusedTrigger) {
            lastFocusedTrigger.focus();
            lastFocusedTrigger = null;
        }
    }

    // Tapping the backdrop dismisses the sheet.
    modalEl.addEventListener('click', function (event) {
        if (event.target === modalEl) hideModal('paymentModal');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modalEl.classList.contains('is-open')) {
            hideModal('paymentModal');
        }
    });

    // ---- Availability polling ---------------------------------------------
    function checkAvailability(packageId) {
        fetch(`{{ route('portal.check-availability', $hotspot->url_name ?: 'hotspot-' . $hotspot->id) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ package_id: packageId })
        })
        .then(response => response.json())
        .then(data => updatePackageAvailability(packageId, data))
        .catch(error => console.error('Error checking availability:', error));
    }

    function updatePackageAvailability(packageId, availability) {
        const card = document.querySelector(`[data-package-id="${packageId}"]`);
        if (!card) return;

        const stock = card.querySelector('.stock-status');
        const go    = card.querySelector('.pt-pkg__go');
        const inStock = !!availability.has_vouchers;

        if (stock) {
            stock.innerHTML = inStock
                ? '<span class="wh-pill wh-pill--ok">Available</span>'
                : '<span class="wh-pill wh-pill--crit">Sold out</span>';
        }

        if (go) {
            go.innerHTML = inStock
                ? 'Buy <i class="fas fa-arrow-right" aria-hidden="true"></i>'
                : 'Unavailable';
        }

        // The row is the control, so availability is just its disabled state.
        card.disabled = !inStock;

        // If the package being bought sells out while the sheet is open, close it
        // rather than let the customer submit a payment that cannot be filled.
        if (!inStock && modalEl.classList.contains('is-open')
            && document.getElementById('modal_package_id').value == packageId) {
            hideModal('paymentModal');
        }
    }

    function startAvailabilityChecking() {
        const packageIds = Array.from(document.querySelectorAll('[data-package-id]'))
            .map(card => card.getAttribute('data-package-id'));

        availabilityCheckInterval = setInterval(() => {
            packageIds.forEach(checkAvailability);
        }, 10000);

        packageIds.forEach(checkAvailability);
    }

    // ---- Opening the sheet --------------------------------------------------
    function openPaymentModal(packageId, packageName, packagePrice) {
        checkAvailability(packageId);

        lastFocusedTrigger = document.querySelector(`[data-package-id="${packageId}"]`);

        document.getElementById('modal_package_id').value = packageId;
        document.getElementById('modal_package_name').textContent = packageName;
        document.getElementById('modal_package_price').textContent = 'UGX ' + packagePrice.toLocaleString();

        phoneInput.value = '';
        clearPhoneError();

        const payButton = document.getElementById('processPaymentBtn');
        payButton.disabled = false;
        payButton.querySelector('.btn-text').style.display = 'inline';
        payButton.querySelector('.btn-loading').style.display = 'none';

        showModal('paymentModal');

        // Focus the one field they have to fill.
        setTimeout(() => phoneInput.focus(), 60);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelector('.close').onclick = () => hideModal('paymentModal');
        document.querySelector('.btn-cancel').onclick = () => hideModal('paymentModal');
        startAvailabilityChecking();
    });

    // ---- Validation ----------------------------------------------------------
    function showPhoneError(message) {
        phoneError.textContent = message;
        phoneError.classList.add('is-shown');
        phoneInput.setAttribute('aria-invalid', 'true');
        phoneInput.focus();
    }

    function clearPhoneError() {
        phoneError.textContent = '';
        phoneError.classList.remove('is-shown');
        phoneInput.removeAttribute('aria-invalid');
    }

    document.getElementById('paymentForm').addEventListener('submit', function (e) {
        let phoneNumber = phoneInput.value;

        if (!phoneNumber) {
            e.preventDefault();
            showPhoneError('Enter the phone number you want to pay from.');
            return;
        }

        phoneNumber = convertToInternationalFormat(phoneNumber);

        const phoneRegex = /^256[0-9]{9}$/;
        if (!phoneRegex.test(phoneNumber)) {
            e.preventDefault();
            showPhoneError('That does not look like a Ugandan number. Try 07XXXXXXXX.');
            return;
        }

        clearPhoneError();
        phoneInput.value = phoneNumber;

        const payButton = document.getElementById('processPaymentBtn');
        payButton.querySelector('.btn-text').style.display = 'none';
        payButton.querySelector('.btn-loading').style.display = 'inline';
        payButton.disabled = true;
    });

    function convertToInternationalFormat(phoneNumber) {
        let cleanNumber = phoneNumber.replace(/\D/g, '');

        if (cleanNumber.startsWith('0')) {
            cleanNumber = '256' + cleanNumber.substring(1);
        } else if (!cleanNumber.startsWith('256')) {
            cleanNumber = '256' + cleanNumber;
        }

        if (cleanNumber.length > 12) {
            cleanNumber = cleanNumber.substring(0, 12);
        }

        return cleanNumber;
    }

    phoneInput.addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 10) value = value.substring(0, 10);
        e.target.value = value;
        if (phoneError.classList.contains('is-shown')) clearPhoneError();
    });

    window.addEventListener('beforeunload', function () {
        if (availabilityCheckInterval) clearInterval(availabilityCheckInterval);
    });
</script>
</body>
</html>
