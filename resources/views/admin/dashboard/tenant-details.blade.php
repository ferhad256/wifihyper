@extends('admin.layouts.app')

@section('title', 'Tenant Details - ' . $tenant->name)

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Tenant Details</h1>
            <p class="text-muted">{{ $tenant->name }} - {{ $tenant->email }}</p>
        </div>
        <div>
            <form method="POST" action="{{ route('admin.tenants.toggle-status', $tenant->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn {{ $tenant->is_active ? 'btn-warning' : 'btn-success' }}" 
                        onclick="return confirm('Are you sure you want to {{ $tenant->is_active ? 'deactivate' : 'activate' }} this tenant?')">
                    <i class="fas fa-{{ $tenant->is_active ? 'pause' : 'play' }} me-2"></i>
                    {{ $tenant->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
            <a href="{{ route('admin.tenants') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Tenants
            </a>
        </div>
    </div>

    <!-- Tenant Information -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-user me-2"></i>Tenant Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $tenant->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>{{ $tenant->email }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td>{{ $tenant->phone ?? 'Not provided' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Business Name:</strong></td>
                                    <td>{{ $tenant->business_name ?? 'Not provided' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        @if($tenant->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Joined:</strong></td>
                                    <td>{{ $tenant->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Last Updated:</strong></td>
                                    <td>{{ $tenant->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Email Verified:</strong></td>
                                    <td>
                                        @if($tenant->email_verified_at)
                                            <span class="badge bg-success">Verified</span>
                                        @else
                                            <span class="badge bg-warning">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    @if($tenant->address)
                    <div class="mt-3">
                        <strong>Address:</strong>
                        <p class="text-muted">{{ $tenant->address }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Hotspots -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-wifi me-2"></i>Hotspots ({{ $tenant->hotspots->count() }})
                    </h6>
                </div>
                <div class="card-body">
                    @if($tenant->hotspots->count() > 0)
                        <div class="row">
                            @foreach($tenant->hotspots as $hotspot)
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-primary">{{ $hotspot->name }}</h6>
                                    <p class="text-muted mb-1">SSID: {{ $hotspot->ssid }}</p>
                                    <p class="text-muted mb-1">Location: {{ $hotspot->location ?? 'Not specified' }}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">{{ $hotspot->packages->count() }} packages</small>
                                        @if($hotspot->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-wifi fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hotspots created yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-chart-bar me-2"></i>Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h3 class="text-success">UGX {{ number_format($tenant_stats['wallet_balance']) }}</h3>
                        <small class="text-muted">Current Wallet Balance</small>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="h4 text-primary">{{ $tenant_stats['total_hotspots'] }}</div>
                            <small class="text-muted">Hotspots</small>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h4 text-success">{{ $tenant_stats['total_vouchers'] }}</div>
                            <small class="text-muted">Vouchers</small>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h4 text-info">{{ $tenant_stats['total_transactions'] }}</div>
                            <small class="text-muted">Transactions</small>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h4 text-warning">UGX {{ number_format($tenant_stats['total_sales']) }}</div>
                            <small class="text-muted">Total Sales</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-exchange-alt me-2"></i>Recent Transactions
                    </h6>
                </div>
                <div class="card-body">
                    @if($tenant->transactions->count() > 0)
                        @foreach($tenant->transactions->take(5) as $transaction)
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div>
                                <small class="text-muted">{{ $transaction->created_at->format('M d, H:i') }}</small><br>
                                <strong>{{ $transaction->hotspot->name ?? 'N/A' }}</strong>
                            </div>
                            <div class="text-end">
                                <strong class="text-success">UGX {{ number_format($transaction->amount) }}</strong><br>
                                {!! $transaction->status_badge !!}
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-exchange-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No transactions yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
