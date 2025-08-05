@extends('layouts.dashboard')

@section('title', 'Settings')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Settings</h1>
    </div>

    <div class="row">
        <!-- Appearance Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-palette me-2"></i>Appearance Settings
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.appearance') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="theme_mode" class="form-label">Theme Mode</label>
                            <select class="form-select" id="theme_mode" name="theme_mode">
                                <option value="light" {{ ($tenant->settings['theme_mode'] ?? 'light') === 'light' ? 'selected' : '' }}>Light Mode</option>
                                <option value="dark" {{ ($tenant->settings['theme_mode'] ?? 'light') === 'dark' ? 'selected' : '' }}>Dark Mode</option>
                                <option value="auto" {{ ($tenant->settings['theme_mode'] ?? 'light') === 'auto' ? 'selected' : '' }}>Auto (Follow System)</option>
                            </select>
                            <div class="form-text">Choose your preferred theme mode for the dashboard.</div>
                        </div>

                        <div class="mb-3">
                            <label for="sidebar_collapsed" class="form-label">Sidebar Behavior</label>
                            <select class="form-select" id="sidebar_collapsed" name="sidebar_collapsed">
                                <option value="0" {{ ($tenant->settings['sidebar_collapsed'] ?? '0') === '0' ? 'selected' : '' }}>Always Expanded</option>
                                <option value="1" {{ ($tenant->settings['sidebar_collapsed'] ?? '0') === '1' ? 'selected' : '' }}>Collapsible</option>
                                <option value="2" {{ ($tenant->settings['sidebar_collapsed'] ?? '0') === '2' ? 'selected' : '' }}>Always Collapsed</option>
                            </select>
                            <div class="form-text">Control how the sidebar behaves by default.</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="compact_mode" name="compact_mode" 
                                       value="1" {{ ($tenant->settings['compact_mode'] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="compact_mode">
                                    Compact Mode
                                </label>
                                <div class="form-text">Reduce spacing and padding for a more compact layout.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Appearance Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-bell me-2"></i>Notification Settings
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.notifications') }}">
                        @csrf
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="low_stock_notifications" name="low_stock_notifications" 
                                       value="1" {{ ($tenant->settings['low_stock_notifications'] ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="low_stock_notifications">
                                    Low Stock Notifications
                                </label>
                                <div class="form-text">Receive notifications when voucher stock is low.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="transaction_notifications" name="transaction_notifications" 
                                       value="1" {{ ($tenant->settings['transaction_notifications'] ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="transaction_notifications">
                                    Transaction Notifications
                                </label>
                                <div class="form-text">Receive notifications for new transactions.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                       value="1" {{ ($tenant->settings['email_notifications'] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="email_notifications">
                                    Email Notifications
                                </label>
                                <div class="form-text">Send notifications via email in addition to in-app notifications.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notification_frequency" class="form-label">Notification Frequency</label>
                            <select class="form-select" id="notification_frequency" name="notification_frequency">
                                <option value="immediate" {{ ($tenant->settings['notification_frequency'] ?? 'immediate') === 'immediate' ? 'selected' : '' }}>Immediate</option>
                                <option value="hourly" {{ ($tenant->settings['notification_frequency'] ?? 'immediate') === 'hourly' ? 'selected' : '' }}>Hourly Digest</option>
                                <option value="daily" {{ ($tenant->settings['notification_frequency'] ?? 'immediate') === 'daily' ? 'selected' : '' }}>Daily Digest</option>
                            </select>
                            <div class="form-text">How often you want to receive notification digests.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Notification Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-cog me-2"></i>System Settings
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Current Subscription Plan Info -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-crown me-2"></i>Current Subscription Plan
                        </h6>
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Plan:</strong>
                                        <span class="badge bg-primary ms-2">{{ $tenant->subscriptionPlan ? $tenant->subscriptionPlan->name : 'Starter' }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Status:</strong>
                                        @if($tenant->subscription_expires_at && $tenant->subscription_expires_at->isFuture())
                                            <span class="badge bg-success ms-2">Active</span>
                                        @else
                                            <span class="badge bg-warning ms-2">Expired</span>
                                        @endif
                                    </div>
                                </div>
                                @if($tenant->subscription_expires_at)
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <strong>Expires:</strong>
                                            <span class="text-muted ms-2">{{ $tenant->subscription_expires_at->format('M d, Y') }}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Days Left:</strong>
                                            <span class="text-muted ms-2">{{ $tenant->subscription_expires_at->diffInDays(now()) }} days</span>
                                        </div>
                                    </div>
                                @endif
                                <div class="mt-3">
                                    <a href="{{ route('subscription.plans') }}" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-arrow-up me-1"></i>Upgrade Plan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('settings.system') }}">
                        @csrf
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       value="1" {{ $tenant->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Account Active
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="Africa/Kampala" {{ ($tenant->settings['timezone'] ?? 'Africa/Kampala') === 'Africa/Kampala' ? 'selected' : '' }}>Africa/Kampala (EAT)</option>
                                <option value="UTC" {{ ($tenant->settings['timezone'] ?? 'Africa/Kampala') === 'UTC' ? 'selected' : '' }}>UTC</option>
                                <option value="America/New_York" {{ ($tenant->settings['timezone'] ?? 'Africa/Kampala') === 'America/New_York' ? 'selected' : '' }}>America/New_York (EST)</option>
                                <option value="Europe/London" {{ ($tenant->settings['timezone'] ?? 'Africa/Kampala') === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT)</option>
                            </select>
                            <div class="form-text">Select your preferred timezone for date and time display.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save System Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-shield-alt me-2"></i>Security Settings
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.security') }}">
                        @csrf
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth" 
                                       value="1" {{ ($tenant->settings['two_factor_auth'] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="two_factor_auth">
                                    Two-Factor Authentication
                                </label>
                                <div class="form-text">Enable two-factor authentication for enhanced security.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="session_timeout" name="session_timeout" 
                                       value="1" {{ ($tenant->settings['session_timeout'] ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="session_timeout">
                                    Auto Logout
                                </label>
                                <div class="form-text">Automatically log out after 30 minutes of inactivity.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_expiry_days" class="form-label">Password Expiry (Days)</label>
                            <select class="form-select" id="password_expiry_days" name="password_expiry_days">
                                <option value="0" {{ ($tenant->settings['password_expiry_days'] ?? '0') === '0' ? 'selected' : '' }}>Never</option>
                                <option value="30" {{ ($tenant->settings['password_expiry_days'] ?? '0') === '30' ? 'selected' : '' }}>30 Days</option>
                                <option value="60" {{ ($tenant->settings['password_expiry_days'] ?? '0') === '60' ? 'selected' : '' }}>60 Days</option>
                                <option value="90" {{ ($tenant->settings['password_expiry_days'] ?? '0') === '90' ? 'selected' : '' }}>90 Days</option>
                            </select>
                            <div class="form-text">Set password expiry period for security.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Security Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Information -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user me-2"></i>Account Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Name:</strong>
                        </div>
                        <div class="col-6">
                            {{ $tenant->name }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Email:</strong>
                        </div>
                        <div class="col-6">
                            {{ $tenant->email }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Business:</strong>
                        </div>
                        <div class="col-6">
                            {{ $tenant->business_name ?? 'Not specified' }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Wallet Balance:</strong>
                        </div>
                        <div class="col-6">
                            UGX {{ number_format($tenant->wallet_balance) }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Plan:</strong>
                        </div>
                        <div class="col-6">
                            <span class="badge bg-primary">{{ $tenant->subscriptionPlan ? $tenant->subscriptionPlan->name : 'Starter' }}</span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Status:</strong>
                        </div>
                        <div class="col-6">
                            @if($tenant->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Member Since:</strong>
                        </div>
                        <div class="col-6">
                            {{ $tenant->created_at->format('M d, Y') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Export -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-download me-2"></i>Data Export
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Export your data for backup or analysis purposes.</p>
                    
                    <div class="d-grid gap-2">
                        <a href="{{ route('vouchers.export') }}" class="btn btn-outline-primary">
                            <i class="fas fa-ticket-alt me-2"></i>Export Vouchers
                        </a>
                        <a href="{{ route('dashboard.export-transactions') }}" class="btn btn-outline-info">
                            <i class="fas fa-chart-bar me-2"></i>Export Transactions
                        </a>
                        <button class="btn btn-outline-warning" onclick="exportAccountData()">
                            <i class="fas fa-user me-2"></i>Export Account Data
                        </button>
                        <button class="btn btn-outline-success" onclick="testEmailConfiguration()">
                            <i class="fas fa-envelope me-2"></i>Test Email Configuration
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function exportAccountData() {
    // This would typically call an API endpoint to export account data
    alert('Account data export feature will be implemented soon.');
}

function testEmailConfiguration() {
    if (confirm('Send a test email to {{ $tenant->email }}?')) {
        fetch('{{ route("settings.test-email") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test email sent successfully! Please check your inbox.');
            } else {
                alert('Failed to send test email: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send test email. Please check your configuration.');
        });
    }
}
</script>
@endpush
@endsection 