@extends('layouts.dashboard')

@section('title', 'Billing & Transactions')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-end mb-4">
        <div>
            <a href="{{ route('transactions.export') }}" class="btn btn-success btn-sm">
                <i class="fas fa-download me-2"></i>Export Transactions
            </a>
        </div>
    </div>

    <!-- Daily Statistics Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="text-gray-800 mb-3">
                <i class="fas fa-calendar-day me-2"></i>Today's Transactions - {{ today()->format('M d, Y') }}
            </h5>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Today - Completed</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $daily_stats['completed'] }}
                            </div>
                            <div class="text-xs text-muted">
                                UGX {{ number_format($daily_stats['total_amount']) }} earned
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Today - Pending</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $daily_stats['pending'] }}
                            </div>
                            <div class="text-xs text-muted">
                                Awaiting payment
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Today - Failed</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $daily_stats['failed'] }}
                            </div>
                            <div class="text-xs text-muted">
                                Payment failed
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Today - Total</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $daily_stats['completed'] + $daily_stats['pending'] + $daily_stats['failed'] }}
                            </div>
                            <div class="text-xs text-muted">
                                All transactions
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- All-Time Statistics Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="text-gray-800 mb-3">
                <i class="fas fa-chart-bar me-2"></i>All-Time Statistics
            </h5>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                All-Time - Completed</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $tenant->transactions()->where('status', 'completed')->count() }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                All-Time - Pending</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $tenant->transactions()->where('status', 'pending')->count() }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                All-Time - Failed</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $tenant->transactions()->where('status', 'failed')->count() }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Current - Wallet Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($tenant->wallet_balance) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Withdrawal Requests -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-warning">
                <i class="fas fa-money-bill-wave me-2"></i>Withdrawal Requests
            </h6>
        </div>
        <div class="card-body">
            @if($withdrawal_requests->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Withdrawal ID</th>
                                <th>Amount</th>
                                <th>Phone Number</th>
                                <th>Status</th>
                                <th>Admin Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($withdrawal_requests as $withdrawal)
                            <tr>
                                <td>{{ $withdrawal->created_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <code>{{ $withdrawal->withdrawal_id }}</code>
                                </td>
                                <td>
                                    <div class="text-warning fw-bold">UGX {{ number_format($withdrawal->amount) }}</div>
                                    @if($withdrawal->fee > 0)
                                        <small class="text-muted">Fee: UGX {{ number_format($withdrawal->fee) }}</small>
                                    @endif
                                </td>
                                <td>{{ $withdrawal->phone_number }}</td>
                                <td>
                                    @switch($withdrawal->status)
                                        @case('pending')
                                            <span class="badge bg-warning">Pending Review</span>
                                            @break
                                        @case('completed')
                                            <span class="badge bg-success">Approved</span>
                                            @break
                                        @case('failed')
                                            <span class="badge bg-danger">Rejected</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ ucfirst($withdrawal->status) }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    @if($withdrawal->admin_notes)
                                        <small class="text-muted">{{ $withdrawal->admin_notes }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-money-bill-wave fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No withdrawal requests found.</p>
                    <p class="text-gray-500">Your withdrawal requests will appear here.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search by buyer's phone number (e.g., 07..., 256...)" value="{{ request('q') }}">
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-search me-1"></i>Search
            </button>
            @if(request('q'))
                <a href="{{ route('dashboard.billing') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            @endif
        </div>
        @if(request('q'))
            <small class="text-muted d-block mt-1">Showing results for: <code>{{ request('q') }}</code></small>
        @endif
    </form>

    <!-- Transactions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Transaction History</h6>
        </div>
        <div class="card-body">
            @if($transactions->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Transaction ID</th>
                                <th>Hotspot</th>
                                <th>Package</th>
                                <th>Voucher Sold</th>
                                <th>Amount</th>
                                <th>Fee</th>
                                <th>Net Amount</th>
                                <th>Phone Number</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <code>{{ $transaction->transaction_id }}</code>
                                </td>
                                <td>{{ $transaction->hotspot->name ?? 'N/A' }}</td>
                                <td>{{ $transaction->package->name ?? 'N/A' }}</td>
                                <td>
                                    @if($transaction->voucher)
                                        <span class="badge bg-success">
                                            <i class="fas fa-ticket-alt me-1"></i>
                                            {{ $transaction->voucher->code }}
                                        </span>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-primary fw-bold">UGX {{ number_format($transaction->amount) }}</div>
                                    <small class="text-muted">100 UGX + 5% fee</small>
                                </td>
                                <td>
                                    <div class="text-warning">UGX {{ number_format($transaction->transaction_fee) }}</div>
                                </td>
                                <td>
                                    <div class="text-success fw-bold">UGX {{ number_format($transaction->net_amount) }}</div>
                                </td>
                                <td>{{ $transaction->phone_number ?? 'N/A' }}</td>
                                <td>{!! $transaction->status_badge !!}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $transactions->links() }}
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No transactions found. Start by creating hotspots and processing payments.</p>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<!-- No scripts needed for this page -->
@endpush
@endsection 