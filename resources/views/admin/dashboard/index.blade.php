@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
        <div class="text-muted">
            <i class="fas fa-clock me-1"></i>
            {{ now()->format('M d, Y - H:i') }}
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Tenants</div>
                        <div class="h4 mb-0 font-weight-bold">{{ $stats['total_tenants'] }}</div>
                        <small>{{ $stats['active_tenants'] }} active</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Revenue</div>
                        <div class="h4 mb-0 font-weight-bold">UGX {{ number_format($stats['total_revenue']) }}</div>
                        <small>{{ $stats['total_transactions'] }} transactions</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Pending Withdrawals</div>
                        <div class="h4 mb-0 font-weight-bold">{{ $stats['pending_withdrawals'] }}</div>
                        <small>UGX {{ number_format($stats['pending_withdrawal_amount']) }}</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Profit (Fees)</div>
                        <div class="h4 mb-0 font-weight-bold">UGX {{ number_format($stats['total_fees']) }}</div>
                        <small>Owner's earnings</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fee Analytics Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="text-gray-800 mb-3">
                <i class="fas fa-chart-pie me-2"></i>Fee Analytics (Owner's Profit) - Transaction Fees + Withdrawal Fees
            </h5>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Fees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">UGX {{ number_format($stats['today_fees']) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">This Week's Fees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">UGX {{ number_format($stats['this_week_fees']) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-week fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">This Month's Fees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">UGX {{ number_format($stats['this_month_fees']) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Average Fee/Transaction</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                UGX {{ $stats['total_transactions'] > 0 ? number_format($stats['total_fees'] / $stats['total_transactions']) : '0' }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fee Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-chart-area me-2"></i>Daily Fees (Transaction + Withdrawal) - Owner's Profit - Last 30 Days
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="feeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Pending Withdrawals -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-money-bill-wave me-2"></i>Pending Withdrawals
                    </h6>
                    <a href="{{ route('admin.withdrawals') }}" class="btn btn-sm btn-light">
                        <i class="fas fa-eye me-1"></i>View All
                    </a>
                </div>
                <div class="card-body">
                    @if($pending_withdrawals->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pending_withdrawals as $withdrawal)
                                    <tr>
                                        <td>
                                            <strong>{{ $withdrawal->tenant->name }}</strong><br>
                                            <small class="text-muted">{{ $withdrawal->tenant->email }}</small>
                                        </td>
                                        <td>
                                            <strong class="text-success">UGX {{ number_format($withdrawal->amount) }}</strong><br>
                                            <small class="text-muted">{{ $withdrawal->phone_number }}</small>
                                        </td>
                                        <td>
                                            {{ $withdrawal->created_at->format('M d, H:i') }}<br>
                                            <small class="text-muted">{{ $withdrawal->created_at->diffForHumans() }}</small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" onclick="approveWithdrawal({{ $withdrawal->id }})">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger" onclick="rejectWithdrawal({{ $withdrawal->id }})">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">No pending withdrawal requests</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Tenants -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-users me-2"></i>Recent Tenants
                    </h6>
                    <a href="{{ route('admin.tenants') }}" class="btn btn-sm btn-light">
                        <i class="fas fa-eye me-1"></i>View All
                    </a>
                </div>
                <div class="card-body">
                    @if($recent_tenants->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent_tenants as $tenant)
                                    <tr>
                                        <td>
                                            <strong>{{ $tenant->name }}</strong><br>
                                            <small class="text-muted">{{ $tenant->email }}</small>
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
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No tenants registered yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-exchange-alt me-2"></i>Recent Transactions
                    </h6>
                    <a href="{{ route('admin.transactions') }}" class="btn btn-sm btn-light">
                        <i class="fas fa-eye me-1"></i>View All
                    </a>
                </div>
                <div class="card-body">
                    @if($recent_transactions->count() > 0)
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Tenant</th>
                                        <th>Hotspot</th>
                                        <th>Package</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent_transactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->created_at->format('M d, H:i') }}</td>
                                        <td>
                                            <strong>{{ $transaction->tenant->name }}</strong><br>
                                            <small class="text-muted">{{ $transaction->tenant->email }}</small>
                                        </td>
                                        <td>{{ $transaction->hotspot->name ?? 'N/A' }}</td>
                                        <td>{{ $transaction->package->name ?? 'N/A' }}</td>
                                        <td><strong class="text-success">UGX {{ number_format($transaction->amount) }}</strong></td>
                                        <td>{!! $transaction->status_badge !!}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No transactions found</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="approvalForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                  placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Approving this withdrawal will deduct the amount from the tenant's wallet balance.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Approve Withdrawal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectionForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reject_notes" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="reject_notes" name="admin_notes" rows="3" 
                                  placeholder="Please provide a reason for rejecting this withdrawal..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>Reject Withdrawal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Fee Chart
var ctx = document.getElementById('feeChart').getContext('2d');
var feeChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($filled_fee_data->pluck('formatted_date')) !!},
        datasets: [{
            label: 'Daily Fees - Transaction + Withdrawal (UGX)',
            data: {!! json_encode($filled_fee_data->pluck('total_fees')) !!},
            backgroundColor: 'rgba(40, 167, 69, 0.8)',
            borderColor: 'rgb(40, 167, 69)',
            borderWidth: 1,
            borderRadius: 4,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Fees Earned: UGX ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'UGX ' + value.toLocaleString();
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index',
        }
    }
});

function approveWithdrawal(id) {
    const form = document.getElementById('approvalForm');
    form.action = `/admin/withdrawals/${id}/approve`;
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function rejectWithdrawal(id) {
    const form = document.getElementById('rejectionForm');
    form.action = `/admin/withdrawals/${id}/reject`;
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}
</script>
@endpush
