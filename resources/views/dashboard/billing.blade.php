@extends('layouts.dashboard')

@section('title', 'Billing & Transactions')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Billing & Transactions</h1>
        <div>
            <a href="{{ route('transactions.export') }}" class="btn btn-success btn-sm">
                <i class="fas fa-download me-2"></i>Export Transactions
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Completed Transactions</div>
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
                                Pending Transactions</div>
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
                                Failed Transactions</div>
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
                                Wallet Balance</div>
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
                                    <small class="text-muted">{{ $transaction->fee_percentage }}% fee</small>
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