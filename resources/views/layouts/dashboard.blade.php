<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard')</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.5rem;
        }
        .topbar {
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        .text-gray-300 {
            color: #dddfeb !important;
        }
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        .chart-area {
            position: relative;
            height: 20rem;
            width: 100%;
        }
        
        /* Notification Dropdown Styles */
        .notification-dropdown {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
        }
        
        .notification-list {
            max-height: 350px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #c1c1c1 #f1f1f1;
        }
        
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .notification-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .notification-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .brand-name {
            color: #007bff !important;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .brand-name:hover {
            color: #0056b3 !important;
        }
        }
        
        .notification-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            color: #333;
        }
        
        .notification-message {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
            line-height: 1.4;
        }
        
        .notification-time {
            font-size: 11px;
            color: #999;
        }
        
        .notification-unread {
            background-color: #f8f9ff;
            border-left: 3px solid #667eea;
        }
        
        .notification-unread .notification-title {
            color: #667eea;
        }
        
        .notification-mark-btn {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 4px;
            background: #667eea;
            color: white;
            border: none;
            transition: all 0.2s ease;
        }
        
        .notification-mark-btn:hover {
            background: #5a6fd8;
            transform: scale(1.05);
        }
        
        /* Dark Mode Styles */
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #cccccc;
            --border-color: #404040;
        }
        
        [data-theme="dark"] body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .card {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .navbar {
            background-color: var(--bg-secondary) !important;
        }
        
        [data-theme="dark"] .sidebar {
            background-color: var(--bg-secondary) !important;
        }
        
        [data-theme="dark"] .text-gray-800 {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] .text-gray-600 {
            color: var(--text-secondary) !important;
        }
        
        /* Compact Mode Styles */
        .compact-mode .card-body {
            padding: 0.75rem;
        }
        
        .compact-mode .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .compact-mode .table td,
        .compact-mode .table th {
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar col-md-3 col-lg-2 d-md-block">
            <div class="p-3">
                <div class="text-center mb-4">
                    <h2 class="brand-name mb-0">WIFIHYPER</h2>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a class="nav-link {{ request()->routeIs('dashboard.billing') ? 'active' : '' }}" href="{{ route('dashboard.billing') }}">
                        <i class="fas fa-chart-bar"></i>
                        Billing
                    </a>
                    <a class="nav-link {{ request()->routeIs('dashboard.hotspots') ? 'active' : '' }}" href="{{ route('dashboard.hotspots') }}">
                        <i class="fas fa-wifi"></i>
                        Hotspots
                    </a>
                    <a class="nav-link {{ request()->routeIs('vouchers.*') ? 'active' : '' }}" href="{{ route('vouchers.index') }}">
                        <i class="fas fa-ticket-alt"></i>
                        Vouchers
                    </a>
                    <a class="nav-link {{ request()->routeIs('dashboard.settings') ? 'active' : '' }}" href="{{ route('dashboard.settings') }}">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                    <a class="nav-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}" href="{{ route('subscription.index') }}">
                        <i class="fas fa-credit-card"></i>
                        Subscription
                    </a>
                    <a class="nav-link {{ request()->routeIs('dashboard.profile') ? 'active' : '' }}" href="{{ route('dashboard.profile') }}">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Topbar -->
            <nav class="topbar navbar navbar-expand navbar-light">
                <div class="container-fluid">
                    <button class="btn btn-link d-md-none" type="button">
                        <i class="fas fa-bars"></i>
                    </button>

                    <ul class="navbar-nav ms-auto">
                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown" id="notificationsDropdown" onclick="loadNotificationsForDropdown()">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="display: none;">
                                    0
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 400px; max-height: 500px;">
                                <li class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span>Notifications</span>
                                    <button class="btn btn-sm btn-link text-decoration-none" onclick="markAllAsRead()">
                                        Mark all as read
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <div id="notificationsList" class="notification-list">
                                    <li class="dropdown-item text-muted">Loading notifications...</li>
                                </div>
                                <li class="dropdown-footer text-center">
                                    <a href="#" class="text-decoration-none">View all notifications</a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-2"></i>
                                {{ $tenant->name ?? 'User' }}
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('dashboard.profile') }}">Profile</a></li>
                                <li><a class="dropdown-item" href="{{ route('dashboard.settings') }}">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Page Content -->
            <div class="p-4">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Notification JavaScript -->
    <script>
        let notificationCheckInterval;
        
        // Load notifications
        function loadNotifications() {
            fetch('{{ route("notifications.count") }}')
                .then(response => response.json())
                .then(data => {
                    updateNotificationBadge(data.count);
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                });
        }

        // Load notifications for dropdown
        function loadNotificationsForDropdown() {
            fetch('{{ route("notifications.get") }}')
                .then(response => response.json())
                .then(data => {
                    updateNotificationsList(data.notifications);
                })
                .catch(error => {
                    console.error('Error loading notifications for dropdown:', error);
                });
        }

        // Update notifications list
        function updateNotificationsList(notifications) {
            const notificationsList = document.getElementById('notificationsList');
            
            if (notifications.length === 0) {
                notificationsList.innerHTML = '<li class="dropdown-item text-muted">No notifications</li>';
                return;
            }

            let html = '';
            notifications.forEach(notification => {
                const unreadClass = notification.status === 'unread' ? 'notification-unread' : '';
                const unreadIcon = notification.status === 'unread' ? '<i class="fas fa-circle text-primary me-2" style="font-size: 8px;"></i>' : '';
                
                html += `
                    <li class="dropdown-item notification-item ${unreadClass}" data-notification-id="${notification.id}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                ${unreadIcon}
                                <div class="notification-title">${notification.title}</div>
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">${notification.created_at}</div>
                            </div>
                            ${notification.status === 'unread' ? 
                                `<button class="notification-mark-btn" onclick="markAsRead(${notification.id})" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>` : ''
                            }
                        </div>
                    </li>
                `;
            });

            notificationsList.innerHTML = html;
        }
        
        // Update notification badge
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationBadge');
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('{{ route("notifications.mark-read") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh notifications list and badge count
                    loadNotificationsForDropdown();
                    loadNotifications();
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        }
        
        // Mark all notifications as read
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
                    // Refresh notifications list and badge count
                    loadNotificationsForDropdown();
                    loadNotifications();
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
            });
        }
        
        // Start notification checking
        function startNotificationChecking() {
            // Check notifications every 30 seconds
            notificationCheckInterval = setInterval(() => {
                loadNotifications();
                loadNotificationsForDropdown();
            }, 30000);
            
            // Initial load
            loadNotifications();
            loadNotificationsForDropdown();
        }
        
        // Clean up interval when page unloads
        window.addEventListener('beforeunload', function() {
            if (notificationCheckInterval) {
                clearInterval(notificationCheckInterval);
            }
        });
        
        // Start notification checking when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startNotificationChecking();
            applyUserSettings();
        });
        
        // Apply user settings (theme, compact mode, etc.)
        function applyUserSettings() {
            // This would typically load settings from the server
            // For now, we'll use localStorage or default values
            const themeMode = localStorage.getItem('theme_mode') || 'light';
            const compactMode = localStorage.getItem('compact_mode') === 'true';
            
            // Apply theme
            document.documentElement.setAttribute('data-theme', themeMode);
            
            // Apply compact mode
            if (compactMode) {
                document.body.classList.add('compact-mode');
            } else {
                document.body.classList.remove('compact-mode');
            }
        }
        
        // Theme switcher function
        function switchTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme_mode', theme);
        }
        
        // Compact mode switcher
        function toggleCompactMode() {
            const isCompact = document.body.classList.toggle('compact-mode');
            localStorage.setItem('compact_mode', isCompact);
        }
    </script>
    
    @stack('scripts')
</body>
</html> 