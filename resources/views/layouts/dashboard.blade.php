<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · WifiHyper</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <meta name="theme-color" content="#0A1628">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}" rel="stylesheet">

    <style>
        /* Notification dropdown — the only chrome unique to this layout.
           Everything else (shell, sidebar, topbar) lives in brand.css and is
           shared with the admin panel. */
        .notification-dropdown {
            width: min(380px, calc(100vw - 2rem));
            padding: 0;
            overflow: hidden;
        }
        .notification-dropdown .dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--wh-line);
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--wh-slate-600);
        }
        .notification-list {
            max-height: 340px;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-width: thin;
        }
        .notification-list::-webkit-scrollbar { width: 6px; }
        .notification-list::-webkit-scrollbar-thumb {
            background: var(--wh-line-strong);
            border-radius: 3px;
        }
        .notification-item {
            display: block;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--wh-line);
            border-radius: 0;
            white-space: normal;
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-unread { background: var(--wh-signal-050); }
        .notification-title {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--wh-ink-900);
            margin-bottom: 0.125rem;
        }
        .notification-message {
            font-size: 0.8125rem;
            color: var(--wh-slate-600);
            line-height: 1.45;
            margin-bottom: 0.25rem;
        }
        .notification-time { font-size: 0.6875rem; color: var(--wh-slate-500); }
        .notification-mark-btn {
            flex: 0 0 auto;
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border: 1px solid var(--wh-line);
            border-radius: var(--wh-r-sm);
            background: var(--wh-paper-raised);
            color: var(--wh-slate-600);
            font-size: 0.6875rem;
            cursor: pointer;
            transition: background-color var(--wh-dur-fast) var(--wh-ease);
        }
        .notification-mark-btn:hover {
            background: var(--wh-ok-050);
            color: var(--wh-ok-600);
            border-color: var(--wh-ok-600);
        }
        .notification-dropdown .dropdown-footer {
            padding: 0.625rem;
            border-top: 1px solid var(--wh-line);
            background: var(--wh-paper-sunken);
            text-align: center;
            font-size: 0.8125rem;
        }
    </style>
    @stack('styles')
</head>
<body>
    <a class="wh-skip" href="#main">Skip to content</a>

    <div class="wh-shell">
        <!-- ============ Sidebar ============ -->
        <aside class="wh-sidebar" id="sidebar" aria-label="Main navigation">
            <div class="wh-sidebar__brand">
                <x-brand.logo :href="route('dashboard')" :size="28" tone="light" />
            </div>

            <p class="wh-sidebar__section">Operations</p>
            <nav class="nav flex-column">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   href="{{ route('dashboard') }}"
                   @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                    <i class="fas fa-gauge-high" aria-hidden="true"></i> Overview
                </a>
                <a class="nav-link {{ request()->routeIs('dashboard.hotspots') || request()->routeIs('hotspots.*') ? 'active' : '' }}"
                   href="{{ route('dashboard.hotspots') }}"
                   @if(request()->routeIs('dashboard.hotspots')) aria-current="page" @endif>
                    <i class="fas fa-tower-broadcast" aria-hidden="true"></i> Hotspots
                </a>
                <a class="nav-link {{ request()->routeIs('vouchers.*') ? 'active' : '' }}"
                   href="{{ route('vouchers.index') }}"
                   @if(request()->routeIs('vouchers.*')) aria-current="page" @endif>
                    <i class="fas fa-ticket-alt" aria-hidden="true"></i> Vouchers
                </a>
            </nav>

            <p class="wh-sidebar__section">Money</p>
            <nav class="nav flex-column">
                <a class="nav-link {{ request()->routeIs('dashboard.billing') ? 'active' : '' }}"
                   href="{{ route('dashboard.billing') }}"
                   @if(request()->routeIs('dashboard.billing')) aria-current="page" @endif>
                    <i class="fas fa-chart-column" aria-hidden="true"></i> Billing
                </a>
                {{-- No "Withdraw" entry: WithdrawalController's six routes all render
                     views under dashboard/withdrawal/ that were never created, so the
                     page 500s. Withdrawals are requested from the wallet panel on
                     Overview, which works, and tracked on Billing. --}}
            </nav>

            <p class="wh-sidebar__section">Account</p>
            <nav class="nav flex-column">
                <a class="nav-link {{ request()->routeIs('dashboard.profile') ? 'active' : '' }}"
                   href="{{ route('dashboard.profile') }}"
                   @if(request()->routeIs('dashboard.profile')) aria-current="page" @endif>
                    <i class="fas fa-user" aria-hidden="true"></i> Profile
                </a>
                <a class="nav-link {{ request()->routeIs('dashboard.settings') ? 'active' : '' }}"
                   href="{{ route('dashboard.settings') }}"
                   @if(request()->routeIs('dashboard.settings')) aria-current="page" @endif>
                    <i class="fas fa-sliders" aria-hidden="true"></i> Settings
                </a>
            </nav>

            <div class="wh-sidebar__foot">
                <span class="wh-dot wh-dot--live me-1"></span> All systems normal
            </div>
        </aside>

        <div class="wh-scrim" id="sidebarScrim" hidden></div>

        <!-- ============ Main ============ -->
        <div class="wh-main">
            <!-- Topbar -->
            <header class="wh-topbar">
                <div class="wh-topbar__inner">
                    <button class="wh-iconbtn d-lg-none" type="button" id="sidebarToggle"
                            aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation menu">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>

                    <div class="me-auto min-w-0">
                        <h1 class="wh-page-title text-truncate">@yield('title', 'Overview')</h1>
                        @hasSection('subtitle')
                            <p class="wh-page-sub text-truncate">@yield('subtitle')</p>
                        @endif
                    </div>

                    <!-- Support -->
                    <div class="dropdown">
                        <button class="wh-iconbtn" type="button" data-bs-toggle="dropdown"
                                aria-expanded="false" aria-label="Support options">
                            <i class="fas fa-headset" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" target="_blank" rel="noopener"
                                   href="https://wa.me/256792746015?text=Hello!%20I%20need%20help%20with%20my%20WifiHyper%20account">
                                    <i class="fab fa-whatsapp" style="color:var(--wh-ok-600);" aria-hidden="true"></i>
                                    WhatsApp 0792 746 015
                                </a>
                            </li>
                            <li>
                                <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#requestCallGlobalModal">
                                    <i class="fas fa-phone" aria-hidden="true"></i> Request a call
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="wh-iconbtn" type="button" data-bs-toggle="dropdown" id="notificationsDropdown"
                                aria-expanded="false" aria-label="Notifications"
                                onclick="loadNotificationsForDropdown()">
                            <i class="fas fa-bell" aria-hidden="true"></i>
                            <span class="wh-badge-count" id="notificationBadge" style="display:none;" aria-live="polite">0</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <li class="dropdown-header">
                                <span>Notifications</span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none"
                                        style="font-size:0.75rem;letter-spacing:0;text-transform:none;min-height:auto;"
                                        onclick="markAllAsRead()">Mark all read</button>
                            </li>
                            <div id="notificationsList" class="notification-list">
                                <li class="notification-item text-muted">Loading notifications…</li>
                            </div>
                            <li class="dropdown-footer">
                                <a href="{{ route('dashboard') }}" class="text-decoration-none">View all activity</a>
                            </li>
                        </ul>
                    </div>

                    <!-- Account -->
                    <div class="dropdown">
                        <button class="wh-userbtn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="wh-avatar" aria-hidden="true">
                                {{ strtoupper(mb_substr($tenant->name ?? 'U', 0, 2)) }}
                            </span>
                            <span class="d-none d-sm-inline text-truncate" style="max-width:12ch;">
                                {{ $tenant->name ?? 'User' }}
                            </span>
                            <i class="fas fa-chevron-down" style="font-size:0.625rem;opacity:0.5;" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('dashboard.profile') }}">
                                <i class="fas fa-user" aria-hidden="true"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('dashboard.settings') }}">
                                <i class="fas fa-sliders" aria-hidden="true"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
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

            <!-- Page content -->
            <main class="wh-content" id="main">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-circle-check me-2" aria-hidden="true"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-circle-exclamation me-2" aria-hidden="true"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- ============ Request a call modal ============ -->
    <div class="modal fade" id="requestCallGlobalModal" tabindex="-1" aria-labelledby="requestCallGlobalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestCallGlobalModalLabel">Request a call from support</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('support.request-call') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="callName">Your name <span class="wh-muted fw-normal">(optional)</span></label>
                            <input type="text" id="callName" name="name" class="form-control" maxlength="100" autocomplete="name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="callContact">Number to call <span class="text-danger">*</span></label>
                            <input type="tel" id="callContact" name="contact" class="form-control" maxlength="30"
                                   placeholder="0792746015" required autocomplete="tel" inputmode="tel">
                            <div class="form-text">The phone number you want us to reach you on.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="callTime">Preferred time <span class="wh-muted fw-normal">(optional)</span></label>
                            <input type="text" id="callTime" name="preferred_time" class="form-control" maxlength="100" placeholder="Today, 4–5pm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="callMsg">Message <span class="wh-muted fw-normal">(optional)</span></label>
                            <textarea id="callMsg" name="message" class="form-control" rows="3" maxlength="500"
                                      placeholder="Briefly describe what you need help with…"></textarea>
                        </div>
                        <div class="alert alert-info mb-0">
                            We'll send your request to <strong>wifihyper01@gmail.com</strong> and call you shortly.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let notificationCheckInterval;

        // ---- Notifications -------------------------------------------------
        function loadNotifications() {
            fetch('{{ route("notifications.count") }}')
                .then(response => response.json())
                .then(data => updateNotificationBadge(data.count))
                .catch(error => console.error('Error loading notifications:', error));
        }

        function loadNotificationsForDropdown() {
            fetch('{{ route("notifications.get") }}')
                .then(response => response.json())
                .then(data => updateNotificationsList(data.notifications))
                .catch(error => console.error('Error loading notifications for dropdown:', error));
        }

        // Values come from the server unescaped, so they are inserted as text
        // nodes rather than concatenated into an HTML string.
        function updateNotificationsList(notifications) {
            const list = document.getElementById('notificationsList');
            list.textContent = '';

            if (!notifications || notifications.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'notification-item text-muted';
                empty.textContent = 'No notifications yet.';
                list.appendChild(empty);
                return;
            }

            notifications.forEach(n => {
                const unread = n.status === 'unread';

                const item = document.createElement('li');
                item.className = 'notification-item' + (unread ? ' notification-unread' : '');
                item.dataset.notificationId = n.id;

                const row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-start gap-2';

                const body = document.createElement('div');
                body.className = 'flex-grow-1 min-w-0';

                const title = document.createElement('div');
                title.className = 'notification-title';
                title.textContent = n.title;

                const message = document.createElement('div');
                message.className = 'notification-message';
                message.textContent = n.message;

                const time = document.createElement('div');
                time.className = 'notification-time';
                time.textContent = n.created_at;

                body.append(title, message, time);
                row.appendChild(body);

                if (unread) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'notification-mark-btn';
                    btn.title = 'Mark as read';
                    btn.setAttribute('aria-label', 'Mark "' + n.title + '" as read');
                    btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
                    btn.addEventListener('click', e => {
                        e.stopPropagation();
                        markAsRead(n.id);
                    });
                    row.appendChild(btn);
                }

                item.appendChild(row);
                list.appendChild(item);
            });
        }

        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationBadge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }

        function markAsRead(notificationId) {
            fetch('{{ route("notifications.mark-read") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotificationsForDropdown();
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        }

        function markAllAsRead() {
            fetch('{{ route("notifications.mark-all-read") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotificationsForDropdown();
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking all notifications as read:', error));
        }

        function startNotificationChecking() {
            notificationCheckInterval = setInterval(() => {
                loadNotifications();
                loadNotificationsForDropdown();
            }, 30000);

            loadNotifications();
            loadNotificationsForDropdown();
        }

        window.addEventListener('beforeunload', function () {
            if (notificationCheckInterval) clearInterval(notificationCheckInterval);
        });

        // ---- Sidebar drawer -------------------------------------------------
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

            // Escape closes the drawer, matching the modals.
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && sidebar.classList.contains('is-open')) setOpen(false);
            });

            // Following a link inside the drawer should dismiss it.
            sidebar.addEventListener('click', e => {
                if (e.target.closest('.nav-link') && window.innerWidth < 992) setOpen(false);
            });

            // Returning to desktop width must clear the drawer state, or the
            // body stays locked from a scroll that no longer applies.
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 992 && sidebar.classList.contains('is-open')) setOpen(false);
            });
        })();

        // ---- User preferences ------------------------------------------------
        function applyUserSettings() {
            const themeMode   = localStorage.getItem('theme_mode') || 'light';
            const compactMode = localStorage.getItem('compact_mode') === 'true';

            document.documentElement.setAttribute('data-theme', themeMode);
            document.body.classList.toggle('compact-mode', compactMode);
        }

        function switchTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme_mode', theme);
        }

        function toggleCompactMode() {
            const isCompact = document.body.classList.toggle('compact-mode');
            localStorage.setItem('compact_mode', isCompact);
        }

        document.addEventListener('DOMContentLoaded', function () {
            startNotificationChecking();
            applyUserSettings();
        });
    </script>

    @stack('scripts')
</body>
</html>
