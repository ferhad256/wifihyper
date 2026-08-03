<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WifiHyper — Sell WiFi access on autopilot</title>
    <meta name="description" content="Run paid WiFi hotspots without standing at the counter. Customers pay by mobile money, vouchers arrive by SMS, and your earnings withdraw straight to your phone.">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <meta name="theme-color" content="#0A1628">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}" rel="stylesheet">
    <link href="{{ asset('css/landing.css') }}" rel="stylesheet">

</head>
<body>
    <a class="wh-skip" href="#main">Skip to content</a>

    <!-- ============ Navigation ============ -->
    <nav class="navbar navbar-expand-lg fixed-top wh-nav" id="siteNav">
        <div class="container">
            <x-brand.logo href="#" :size="30" tone="ink" class="navbar-brand p-0 me-3" />

            <button class="navbar-toggler border-0 shadow-none" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How it works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-secondary btn-sm" href="{{ route('login') }}">Log in</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Create account</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="main">
    <!-- ============ Hero ============ -->
    <section class="wh-hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <p class="wh-eyebrow wh-eyebrow--onink mb-3">
                        <span class="wh-dot wh-dot--live"></span>
                        Hotspot billing for Uganda
                    </p>

                    <h1>Your WiFi keeps selling<br><em>after you go home.</em></h1>

                    <p class="wh-hero__lead">
                        Customers scan, pay with mobile money, and get their voucher by SMS —
                        without you handing out a single code. Your earnings land back on your
                        phone whenever you withdraw them.
                    </p>

                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                            Start selling free
                        </a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg">
                            See how it works
                        </a>
                    </div>

                    <div class="wh-hero__proof">
                        <span><i class="fas fa-check"></i> No monthly fee</span>
                        <span><i class="fas fa-check"></i> No setup cost</span>
                        <span><i class="fas fa-check"></i> Charged only per sale</span>
                    </div>
                </div>

                <!-- Product preview: the operator's console, at rest -->
                <div class="col-lg-6">
                    <div class="wh-console" role="img"
                         aria-label="Preview of the WifiHyper operator dashboard showing today's takings, active hotspots and recent voucher sales.">
                        <div class="wh-console__bar">
                            <p class="wh-console__title">Today</p>
                            <span class="wh-console__live">
                                <span class="wh-dot wh-dot--live"></span> Live
                            </span>
                        </div>

                        <div class="wh-console__summary">
                            <div>
                                <span class="wh-console__k">Collected</span>
                                <span class="wh-console__v"><small>UGX</small> 184,000</span>
                            </div>
                            <div>
                                <span class="wh-console__k">Vouchers sold</span>
                                <span class="wh-console__v">92</span>
                            </div>
                        </div>

                        <div class="wh-console__row">
                            <span class="wh-dot wh-dot--ok"></span>
                            <span>
                                <span class="wh-console__name d-block">Kabalagala Cafe</span>
                                <span class="wh-console__sub">Online · 24 active</span>
                            </span>
                            <span class="wh-console__amt">76,000</span>
                        </div>
                        <div class="wh-console__row">
                            <span class="wh-dot wh-dot--ok"></span>
                            <span>
                                <span class="wh-console__name d-block">Ntinda Plaza</span>
                                <span class="wh-console__sub">Online · 17 active</span>
                            </span>
                            <span class="wh-console__amt">61,000</span>
                        </div>
                        <div class="wh-console__row">
                            <span class="wh-dot wh-dot--warn"></span>
                            <span>
                                <span class="wh-console__name d-block">Bweyogerere Lodge</span>
                                <span class="wh-console__sub">Low voucher stock · 6 left</span>
                            </span>
                            <span class="wh-console__amt">47,000</span>
                        </div>
                    </div>
                    <p class="text-center mt-2 mb-0" style="font-size:0.75rem;color:rgba(232,237,243,0.42);">
                        Dashboard preview
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Capability strip ============ -->
    <section class="wh-strip">
        <div class="container">
            <div class="row g-0">
                <div class="col-md-3 col-6">
                    <div class="wh-strip__item">
                        <span class="wh-strip__v">Mobile money</span>
                        <p class="wh-strip__k">Paid straight from the customer's phone</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="wh-strip__item">
                        <span class="wh-strip__v">SMS delivery</span>
                        <p class="wh-strip__k">Voucher code sent the moment payment clears</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="wh-strip__item">
                        <span class="wh-strip__v">Unlimited hotspots</span>
                        <p class="wh-strip__k">One account covers every site you run</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="wh-strip__item">
                        <span class="wh-strip__v">Withdraw anytime</span>
                        <p class="wh-strip__k">Earnings back to your mobile money account</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ How it works ============ -->
    <section id="how-it-works" class="wh-section">
        <div class="container">
            <div class="wh-section__head">
                <p class="wh-eyebrow">How it works</p>
                <h2>Set it up once. It runs on its own.</h2>
                <p>Four steps between creating an account and taking your first payment.</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-sm-6">
                    <div class="wh-step wh-step--done">
                        <span class="wh-step__n">STEP 01</span>
                        <h3>Create your account</h3>
                        <p>Sign up with your phone number and email. Nothing to install.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="wh-step wh-step--done">
                        <span class="wh-step__n">STEP 02</span>
                        <h3>Add a hotspot</h3>
                        <p>Name your site and set the packages you want to sell — by hour, day or week.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="wh-step wh-step--done">
                        <span class="wh-step__n">STEP 03</span>
                        <h3>Upload your vouchers</h3>
                        <p>Paste them in or import a CSV from your router. They queue up ready to sell.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="wh-step wh-step--done">
                        <span class="wh-step__n">STEP 04</span>
                        <h3>Get paid</h3>
                        <p>Customers buy from your portal, codes go out by SMS, and you withdraw when you like.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Features ============ -->
    <section id="features" class="wh-section wh-section--sunken">
        <div class="container">
            <div class="wh-section__head">
                <p class="wh-eyebrow">What you get</p>
                <h2>Everything the counter used to do</h2>
                <p>The parts of running paid WiFi that eat your day, handled without you.</p>
            </div>

            <div class="row g-3 g-lg-4">
                <div class="col-lg-4 col-md-6">
                    <div class="wh-feature">
                        <div class="wh-feature__icon"><i class="fas fa-ticket-alt"></i></div>
                        <h3>Voucher management</h3>
                        <p>Bulk CSV import, paste-in entry, per-package stock, and a warning before you run dry.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="wh-feature">
                        <div class="wh-feature__icon"><i class="fas fa-comment-sms"></i></div>
                        <h3>Automatic SMS delivery</h3>
                        <p>The code reaches the buyer seconds after payment confirms. No queue, no arguing.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="wh-feature">
                        <div class="wh-feature__icon"><i class="fas fa-mobile-screen-button"></i></div>
                        <h3>Mobile money payments</h3>
                        <p>Customers pay with the wallet already on their phone. Every transaction is logged against the sale.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="wh-feature">
                        <div class="wh-feature__icon"><i class="fas fa-tower-broadcast"></i></div>
                        <h3>Multi-site control</h3>
                        <p>Run every hotspot from one login, each with its own packages, prices and portal page.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="wh-feature">
                        <div class="wh-feature__icon"><i class="fas fa-chart-line"></i></div>
                        <h3>Sales you can check</h3>
                        <p>Takings by day, by site and by package — with a full transaction history you can export.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="wh-feature">
                        <div class="wh-feature__icon"><i class="fas fa-wallet"></i></div>
                        <h3>Withdrawals to your phone</h3>
                        <p>Request a payout from your balance and track it from pending through to paid.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Pricing ============ -->
    <section id="pricing" class="wh-section">
        <div class="container">
            <div class="wh-section__head">
                <p class="wh-eyebrow">Pricing</p>
                <h2>You only pay when you get paid</h2>
                <p>No subscription, no setup fee, no minimum. A transaction fee is taken per sale — that is the whole model.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="wh-price">
                        <div class="wh-price__head text-center">
                            <span class="wh-pill wh-pill--info">Every feature included</span>
                            <div class="wh-price__amount">Free to start</div>
                            <p class="mb-0 wh-muted">A transaction fee applies per completed sale. Nothing else.</p>
                        </div>

                        <div class="wh-price__body">
                            <div class="row g-lg-4">
                                <div class="col-md-6">
                                    <ul class="wh-price__list">
                                        <li><i class="fas fa-check"></i> Unlimited hotspots</li>
                                        <li><i class="fas fa-check"></i> Unlimited vouchers</li>
                                        <li><i class="fas fa-check"></i> Automatic SMS delivery</li>
                                        <li><i class="fas fa-check"></i> Mobile money payments</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="wh-price__list">
                                        <li><i class="fas fa-check"></i> Sales reports and exports</li>
                                        <li><i class="fas fa-check"></i> Your own branded portal page</li>
                                        <li><i class="fas fa-check"></i> Withdrawals to mobile money</li>
                                        <li><i class="fas fa-check"></i> WhatsApp and email support</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-sm-flex mt-4">
                                <a href="{{ route('register') }}" class="btn btn-primary btn-lg flex-fill">
                                    Create your free account
                                </a>
                                <button type="button" class="btn btn-secondary btn-lg flex-fill" onclick="contactSales()">
                                    Talk to us first
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Closing CTA ============ -->
    <section class="wh-section pt-0">
        <div class="container">
            <div class="wh-cta">
                <h2>Put your hotspot to work tonight</h2>
                <p>Setting up takes a few minutes. Your first sale can happen before you close.</p>
                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Start selling free</a>
                    <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg">Log in</a>
                </div>
            </div>
        </div>
    </section>
    </main>

    <!-- ============ Footer ============ -->
    <footer class="wh-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5 col-md-12">
                    <x-brand.logo :size="30" tone="light" class="mb-3" />
                    <p class="mb-0" style="max-width:42ch;font-size:0.9rem;">
                        Hotspot billing for people who sell internet access — payments, vouchers
                        and payouts in one place.
                    </p>
                </div>

                <div class="col-lg-2 col-6">
                    <h6>Product</h6>
                    <ul class="list-unstyled mb-0">
                        <li><a href="#how-it-works">How it works</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-6">
                    <h6>Account</h6>
                    <ul class="list-unstyled mb-0">
                        <li><a href="{{ route('login') }}">Log in</a></li>
                        <li><a href="{{ route('register') }}">Create account</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6>Support</h6>
                    <ul class="list-unstyled mb-0">
                        <li>
                            <a href="https://wa.me/256792746413" target="_blank" rel="noopener">
                                <i class="fab fa-whatsapp me-2" aria-hidden="true"></i>WhatsApp 0792 746 413
                            </a>
                        </li>
                        <li>
                            <a href="mailto:wifihyper01@gmail.com">
                                <i class="fas fa-envelope me-2" aria-hidden="true"></i>wifihyper01@gmail.com
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="wh-footer__rule"></div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <p class="wh-footer__fine mb-0">&copy; {{ date('Y') }} WifiHyper. All rights reserved.</p>
                <div class="d-flex gap-3">
                    <a href="#" style="font-size:0.8125rem;">Privacy Policy</a>
                    <a href="#" style="font-size:0.8125rem;">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- WhatsApp quick contact -->
    <a href="https://wa.me/256792746413?text=Hello!%20I%20need%20help%20with%20WifiHyper"
       target="_blank" rel="noopener" class="wh-wa" aria-label="Chat with support on WhatsApp">
        <i class="fab fa-whatsapp" aria-hidden="true"></i>
    </a>

    <!-- ============ Contact sales modal ============ -->
    <div class="modal fade" id="contactSalesModal" tabindex="-1" aria-labelledby="contactSalesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactSalesModalLabel">Talk to our team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="wh-muted mb-4" style="font-size:0.9375rem;">
                        Questions about pricing, setting up a site, or moving vouchers over from another
                        system? Reach us on whichever is easiest.
                    </p>

                    <div class="d-grid gap-2">
                        <a href="https://wa.me/256704791624?text=Hello!%20I%20have%20a%20question%20about%20WifiHyper."
                           target="_blank" rel="noopener" class="btn btn-success btn-lg">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp us
                        </a>

                        <div class="row g-2">
                            <div class="col-sm-6 d-grid">
                                <a href="tel:0392998816" class="btn btn-secondary">
                                    <i class="fas fa-phone" aria-hidden="true"></i> 0392 998 816
                                </a>
                            </div>
                            <div class="col-sm-6 d-grid">
                                <a href="tel:0783052764" class="btn btn-secondary">
                                    <i class="fas fa-phone" aria-hidden="true"></i> 0783 052 764
                                </a>
                            </div>
                        </div>

                        <a href="mailto:support@wifihyper.com?subject=Question%20about%20WifiHyper"
                           class="btn btn-secondary">
                            <i class="fas fa-envelope" aria-hidden="true"></i> Email us
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function contactSales() {
            new bootstrap.Modal(document.getElementById('contactSalesModal')).show();
        }

        // Nav gains its bottom rule only once the page has scrolled, so the
        // hero meets the bar cleanly at rest.
        (function () {
            var nav = document.getElementById('siteNav');
            var onScroll = function () {
                nav.classList.toggle('is-stuck', window.scrollY > 8);
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        })();
    </script>
</body>
</html>
