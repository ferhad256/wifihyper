@extends('admin.layouts.app')

@section('title', 'Transaction Monitoring')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-end mb-4">
        <div class="text-muted">
            <i class="fas fa-exchange-alt me-1"></i>
            {{ $transactions->total() }} total transactions
        </div>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search by phone number (e.g., 07..., 256...)" value="{{ request('q') }}">
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-search me-1"></i>Search
            </button>
            @if(request('q'))
                <a href="{{ route('admin.transactions') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            @endif
        </div>
        @if(request('q'))
            <small class="text-muted d-block mt-1">Showing results for: <code>{{ request('q') }}</code></small>
        @endif
    </form>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-list me-2"></i>All Transactions
            </h6>
        </div>
        <div class="card-body">
            @if($transactions->count() > 0)
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Tenant</th>
                                <th>Hotspot</th>
                                <th>Package</th>
                                <th>Amount</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transactions as $transaction)
                            <tr>
                                <td>
                                    {{ $transaction->created_at->format('M d, Y') }}<br>
                                    <small class="text-muted">{{ $transaction->created_at->format('H:i') }}</small>
                                </td>
                                <td>
                                    <code>{{ $transaction->transaction_id }}</code>
                                </td>
                                <td>
                                    <strong>{{ $transaction->tenant->name }}</strong><br>
                                    <small class="text-muted">{{ $transaction->tenant->email }}</small>
                                </td>
                                <td>{{ $transaction->hotspot->name ?? 'N/A' }}</td>
                                <td>{{ $transaction->package->name ?? 'N/A' }}</td>
                                <td>
                                    <strong class="text-success">UGX {{ number_format($transaction->amount) }}</strong>
                                    @if($transaction->transaction_fee > 0)
                                        <br><small class="text-muted">Fee: UGX {{ number_format($transaction->transaction_fee) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($transaction->phone_number)
                                        <code>{{ $transaction->phone_number }}</code>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>{!! $transaction->status_badge !!}</td>
                                <td>
                                    @if($transaction->type)
                                        <span class="badge bg-{{ $transaction->type === 'subscription' ? 'warning' : 'primary' }}">
                                            {{ ucfirst($transaction->type) }}
                                        </span>
                                    @else
                                        <span class="badge bg-primary">Voucher</span>
                                    @endif
                                </td>
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
                <div class="text-center py-5">
                    <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No transactions found</h5>
                    <p class="text-muted">Transactions will appear here as tenants process payments.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
