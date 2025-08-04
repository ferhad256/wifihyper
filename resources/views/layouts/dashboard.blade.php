<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WiFi SaaS Dashboard')</title>
    
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
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar col-md-3 col-lg-2 d-md-block">
            <div class="p-3">
                <h4 class="text-white mb-4">
                    <i class="fas fa-wifi me-2"></i>
                    WiFi SaaS
                </h4>
                
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
                            <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown" id="notificationsDropdown">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="display: none;">
                                    0
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="width: 350px;">
                                <li class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span>Notifications</span>
                                    <button class="btn btn-sm btn-link text-decoration-none" onclick="markAllAsRead()">
                                        Mark all as read
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <div id="notificationsList">
                                    <!-- Notifications will be loaded here -->
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
                    // Remove the notification from the list
                    const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.remove();
                    }
                    
                    // Update badge count
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
                    // Clear notifications list
                    document.getElementById('notificationsList').innerHTML = '<li class="dropdown-item text-muted">No notifications</li>';
                    
                    // Update badge count
                    updateNotificationBadge(0);
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
            }, 30000);
            
            // Initial load
            loadNotifications();
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
        });
    </script>
    
    @stack('scripts')
</body>
</html> 