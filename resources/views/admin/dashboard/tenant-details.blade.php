@extends('admin.layouts.app')

@section('title', 'Tenant Details - ' . $tenant->name)

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-end mb-4">
        <div>
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
            <form method="POST" action="{{ route('admin.tenants.destroy', $tenant->id) }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('This will permanently delete the tenant and all related data. Are you sure?')">
                    <i class="fas fa-trash me-2"></i>Delete Tenant
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
                        @foreach($tenant->hotspots as $hotspot)
                        <div class="border rounded p-4 mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="text-primary mb-1">{{ $hotspot->name }}</h6>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-wifi me-1"></i>SSID: <code>{{ $hotspot->ssid }}</code>
                                            </p>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-map-marker-alt me-1"></i>Location: {{ $hotspot->location ?? 'Not specified' }}
                                            </p>
                                            <p class="text-muted mb-1">
                                                <i class="fas fa-calendar me-1"></i>Created: {{ $hotspot->created_at->format('M d, Y H:i') }}
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            @if($hotspot->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Packages for this hotspot -->
                                    @if($hotspot->packages->count() > 0)
                                    <div class="mb-3">
                                        <h6 class="text-dark mb-2">
                                            <i class="fas fa-box me-1"></i>Packages ({{ $hotspot->packages->count() }})
                                        </h6>
                                        <div class="row">
                                            @foreach($hotspot->packages as $package)
                                            <div class="col-md-6 mb-2">
                                                <div class="bg-light p-2 rounded">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <small class="text-dark fw-bold">{{ $package->name }}</small><br>
                                                            <small class="text-muted">UGX {{ number_format($package->price) }}</small>
                                                        </div>
                                                        @if($package->is_active)
                                                            <span class="badge bg-success badge-sm">Active</span>
                                                        @else
                                                            <span class="badge bg-secondary badge-sm">Inactive</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Vouchers for this hotspot -->
                                    @if($hotspot->vouchers->count() > 0)
                                    <div>
                                        <h6 class="text-dark mb-2">
                                            <i class="fas fa-ticket-alt me-1"></i>Vouchers ({{ $hotspot->vouchers->count() }})
                                        </h6>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <span class="badge bg-success">{{ $hotspot->vouchers->where('status', 'unused')->count() }} Unused</span>
                                            <span class="badge bg-primary">{{ $hotspot->vouchers->where('status', 'used')->count() }} Used</span>
                                            <span class="badge bg-warning">{{ $hotspot->vouchers->where('status', 'expired')->count() }} Expired</span>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="h4 text-primary mb-1">{{ $hotspot->vouchers->count() }}</div>
                                        <small class="text-muted">Total Vouchers</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-wifi fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hotspots created yet</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Voucher Details -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-ticket-alt me-2"></i>Voucher Details ({{ $tenant->vouchers->count() }})
                    </h6>
                </div>
                <div class="card-body">
                    @if($tenant->vouchers->count() > 0)
                        <!-- Voucher Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3 text-center">
                                <div class="h4 text-success">{{ $voucher_stats->get('unused', (object)['count' => 0])->count ?? 0 }}</div>
                                <small class="text-muted">Unused</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="h4 text-primary">{{ $voucher_stats->get('used', (object)['count' => 0])->count ?? 0 }}</div>
                                <small class="text-muted">Used</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="h4 text-warning">{{ $voucher_stats->get('expired', (object)['count' => 0])->count ?? 0 }}</div>
                                <small class="text-muted">Expired</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="h4 text-info">{{ $tenant->vouchers->count() }}</div>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>

                        <!-- Recent Vouchers -->
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Package</th>
                                        <th>Hotspot</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Used/Expired</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tenant->vouchers->take(10) as $voucher)
                                    <tr>
                                        <td>
                                            <code class="text-primary">{{ $voucher->code }}</code>
                                        </td>
                                        <td>
                                            <small>{{ $voucher->package->name ?? 'N/A' }}</small><br>
                                            <small class="text-muted">UGX {{ number_format($voucher->package->price ?? 0) }}</small>
                                        </td>
                                        <td>
                                            <small>{{ $voucher->hotspot->name ?? 'N/A' }}</small>
                                        </td>
                                        <td>
                                            @switch($voucher->status)
                                                @case('unused')
                                                    <span class="badge bg-success">Unused</span>
                                                    @break
                                                @case('used')
                                                    <span class="badge bg-primary">Used</span>
                                                    @break
                                                @case('expired')
                                                    <span class="badge bg-warning">Expired</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary">{{ ucfirst($voucher->status) }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            <small>{{ $voucher->created_at->format('M d, H:i') }}</small>
                                        </td>
                                        <td>
                                            @if($voucher->used_at)
                                                <small class="text-success">{{ $voucher->used_at->format('M d, H:i') }}</small>
                                            @elseif($voucher->expires_at && $voucher->expires_at->isPast())
                                                <small class="text-warning">Expired</small>
                                            @else
                                                <small class="text-muted">-</small>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($tenant->vouchers->count() > 10)
                        <div class="text-center mt-3">
                            <small class="text-muted">Showing 10 of {{ $tenant->vouchers->count() }} vouchers</small>
                        </div>
                        @endif
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-ticket-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No vouchers created yet</p>
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
                    
                    <!-- Hotspot Statistics -->
                    <div class="mb-4">
                        <h6 class="text-dark mb-2">Hotspots</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h5 text-success">{{ $tenant_stats['active_hotspots'] }}</div>
                                <small class="text-muted">Active</small>
                            </div>
                            <div class="col-6">
                                <div class="h5 text-secondary">{{ $tenant_stats['inactive_hotspots'] }}</div>
                                <small class="text-muted">Inactive</small>
                            </div>
                        </div>
                    </div>

                    <!-- Voucher Statistics -->
                    <div class="mb-4">
                        <h6 class="text-dark mb-2">Vouchers</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h5 text-success">{{ $tenant_stats['unused_vouchers'] }}</div>
                                <small class="text-muted">Unused</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-primary">{{ $tenant_stats['used_vouchers'] }}</div>
                                <small class="text-muted">Used</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 text-warning">{{ $tenant_stats['expired_vouchers'] }}</div>
                                <small class="text-muted">Expired</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row text-center">
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
