@extends('admin.layouts.app')

@section('title', 'Tenant Management')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Tenant Management</h1>
        <div class="text-muted">
            <i class="fas fa-users me-1"></i>
            {{ $tenants->total() }} total tenants
        </div>
    </div>

    <!-- Tenants Table -->
    <div class="card">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-list me-2"></i>All Tenants
            </h6>
        </div>
        <div class="card-body">
            @if($tenants->count() > 0)
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tenant Info</th>
                                <th>Business</th>
                                <th>Wallet Balance</th>
                                <th>Hotspots</th>
                                <th>Transactions</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tenants as $tenant)
                            <tr class="{{ !$tenant->is_active ? 'table-secondary' : '' }}">
                                <td>
                                    <strong>{{ $tenant->name }}</strong><br>
                                    <small class="text-muted">{{ $tenant->email }}</small><br>
                                    @if($tenant->phone)
                                        <small class="text-info">{{ $tenant->phone }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($tenant->business_name)
                                        <strong>{{ $tenant->business_name }}</strong><br>
                                    @endif
                                    @if($tenant->address)
                                        <small class="text-muted">{{ Str::limit($tenant->address, 30) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <strong class="text-success">UGX {{ number_format($tenant->wallet_balance) }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ $tenant->hotspots_count }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $tenant->transactions_count }}</span><br>
                                    <small class="text-muted">
                                        UGX {{ number_format($tenant->transactions()->where('status', 'completed')->sum('amount')) }}
                                    </small>
                                </td>
                                <td>
                                    @if($tenant->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $tenant->created_at->format('M d, Y') }}<br>
                                    <small class="text-muted">{{ $tenant->created_at->diffForHumans() }}</small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.tenants.toggle-status', $tenant->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn {{ $tenant->is_active ? 'btn-warning' : 'btn-success' }}" 
                                                    onclick="return confirm('Are you sure you want to {{ $tenant->is_active ? 'deactivate' : 'activate' }} this tenant?')">
                                                <i class="fas fa-{{ $tenant->is_active ? 'pause' : 'play' }}"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $tenants->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No tenants found</h5>
                    <p class="text-muted">Tenants will appear here when they register for the platform.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
