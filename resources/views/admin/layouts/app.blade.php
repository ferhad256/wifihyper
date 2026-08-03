<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · WifiHyper Admin</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <meta name="theme-color" content="#060D18">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}" rel="stylesheet">

    <style>
        /* The admin panel sits one shade darker than the tenant dashboard, so
           it is obvious at a glance which side of the product you are on. */
        .wh-sidebar { background: var(--wh-ink-900); }

        .wh-admin-tag {
            display: inline-block;
            margin-top: 0.375rem;
            padding: 0.1rem 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--wh-r-sm);
            font-size: 0.5625rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
        }

        .wh-chart { position: relative; height: 25rem; width: 100%; }
        @media (max-width: 767.98px) { .wh-chart { height: 18rem; } }
    </style>
    @stack('styles')
</head>
<body>
    <a class="wh-skip" href="#main">Skip to content</a>

    @php($admin = Auth::guard('admin')->user())

    <div class="wh-shell">
        <!-- ============ Sidebar ============ -->
        <aside class="wh-sidebar" id="sidebar" aria-label="Admin navigation">
            <div class="wh-sidebar__brand">
                <x-brand.logo :href="route('admin.dashboard')" :size="28" tone="light" />
                <span class="wh-admin-tag">Admin panel</span>
            </div>

            <p class="wh-sidebar__section">Monitor</p>
            <nav class="nav flex-column">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                   href="{{ route('admin.dashboard') }}"
                   @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    <i class="fas fa-gauge-high" aria-hidden="true"></i> Dashboard
                </a>
                <a class="nav-link {{ request()->routeIs('admin.transactions') ? 'active' : '' }}"
                   href="{{ route('admin.transactions') }}"
                   @if(request()->routeIs('admin.transactions')) aria-current="page" @endif>
                    <i class="fas fa-right-left" aria-hidden="true"></i> Transactions
                </a>
            </nav>

            <p class="wh-sidebar__section">Manage</p>
            <nav class="nav flex-column">
                <a class="nav-link {{ request()->routeIs('admin.tenants*') ? 'active' : '' }}"
                   href="{{ route('admin.tenants') }}"
                   @if(request()->routeIs('admin.tenants*')) aria-current="page" @endif>
                    <i class="fas fa-users" aria-hidden="true"></i> Tenants
                </a>
                <a class="nav-link {{ request()->routeIs('admin.withdrawals') ? 'active' : '' }}"
                   href="{{ route('admin.withdrawals') }}"
                   @if(request()->routeIs('admin.withdrawals')) aria-current="page" @endif>
                    <i class="fas fa-money-bill-wave" aria-hidden="true"></i> Withdrawals
                </a>
            </nav>

            <p class="wh-sidebar__section">Account</p>
            <nav class="nav flex-column">
                <a class="nav-link {{ request()->routeIs('admin.profile') ? 'active' : '' }}"
                   href="{{ route('admin.profile') }}"
                   @if(request()->routeIs('admin.profile')) aria-current="page" @endif>
                    <i class="fas fa-user-gear" aria-hidden="true"></i> Profile
                </a>
                @if($admin->isSuperAdmin())
                <a class="nav-link {{ request()->routeIs('admin.register') ? 'active' : '' }}"
                   href="{{ route('admin.register') }}"
                   @if(request()->routeIs('admin.register')) aria-current="page" @endif>
                    <i class="fas fa-user-plus" aria-hidden="true"></i> Add admin
                </a>
                @endif
            </nav>

            <div class="wh-sidebar__foot">
                Signed in as {{ $admin->name }}
            </div>
        </aside>

        <div class="wh-scrim" id="sidebarScrim" hidden></div>

        <!-- ============ Main ============ -->
        <div class="wh-main">
            <header class="wh-topbar">
                <div class="wh-topbar__inner">
                    <button class="wh-iconbtn d-lg-none" type="button" id="sidebarToggle"
                            aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation menu">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>

                    <div class="me-auto min-w-0">
                        <h1 class="wh-page-title text-truncate">@yield('title')</h1>
                        @hasSection('subtitle')
                            <p class="wh-page-sub text-truncate">@yield('subtitle')</p>
                        @endif
                    </div>

                    <div class="dropdown">
                        <button class="wh-userbtn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="wh-avatar" aria-hidden="true">
                                {{ strtoupper(mb_substr($admin->name, 0, 2)) }}
                            </span>
                            <span class="d-none d-sm-inline text-truncate" style="max-width:12ch;">
                                {{ $admin->name }}
                            </span>
                            @if($admin->isSuperAdmin())
                                <span class="wh-pill wh-pill--value d-none d-md-inline-flex">Super</span>
                            @endif
                            <i class="fas fa-chevron-down" style="font-size:0.625rem;opacity:0.5;" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('admin.profile') }}">
                                <i class="fas fa-user-gear" aria-hidden="true"></i> Profile settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="wh-content" id="main">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-circle-check me-2" aria-hidden="true"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-triangle-exclamation me-2" aria-hidden="true"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ---- Sidebar drawer ------------------------------------------------
        (function () {
            const sidebar = document.getElementById('sidebar');
            const scrim   = document.getElementById('sidebarScrim');
            const toggle  = document.getElementById('sidebarToggle');

            function setOpen(open) {
                sidebar.classList.toggle('is-open', open);
                scrim.classList.toggle('is-open', open);
                scrim.hidden = !open;
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
                document.body.style.overflow = open ? 'hidden' : '';
                if (open) {
                    const first = sidebar.querySelector('.nav-link');
                    if (first) first.focus();
                } else {
                    toggle.focus();
                }
            }

            toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
            scrim.addEventListener('click', () => setOpen(false));

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && sidebar.classList.contains('is-open')) setOpen(false);
            });

            sidebar.addEventListener('click', e => {
                if (e.target.closest('.nav-link') && window.innerWidth < 992) setOpen(false);
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 992 && sidebar.classList.contains('is-open')) setOpen(false);
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
