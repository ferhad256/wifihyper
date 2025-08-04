@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <div>
            <a href="{{ route('vouchers.index') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-ticket-alt me-2"></i>Manage Vouchers
            </a>
            <a href="{{ route('dashboard.hotspots') }}" class="btn btn-success btn-sm">
                <i class="fas fa-wifi me-2"></i>Add Hotspot
            </a>
        </div>
    </div>



    <!-- Balance Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Current Wallet Balance
                            </div>
                            <div class="h2 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($tenant->wallet_balance) }}
                            </div>
                            <small class="text-muted">Transaction fees already excluded</small>
                        </div>
                        <div class="col-md-4 text-end">
                            @if($tenant->wallet_balance >= 10000)
                                <button class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                    <i class="fas fa-money-bill-wave me-2"></i>Request Withdraw
                                </button>
                            @else
                                <button class="btn btn-secondary btn-lg" disabled>
                                    <i class="fas fa-money-bill-wave me-2"></i>Min. UGX 10,000
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Available Vouchers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['unused_vouchers'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
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
                                Active Hotspots</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['total_hotspots'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wifi fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Sales Overview (Last 30 Days)</h6>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportChart('png')">PNG Image</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportChart('pdf')">PDF Document</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('vouchers.index') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-upload me-2"></i>Upload Vouchers
                        </a>
                        <a href="{{ route('dashboard.hotspots') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-2"></i>Create Hotspot
                        </a>
                        <a href="{{ route('dashboard.billing') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-chart-bar me-2"></i>View Transactions
                        </a>
                        <a href="{{ route('dashboard.settings') }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </div>
                </div>

            <!-- Sales Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sales Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="text-xs font-weight-bold text-success text-uppercase">
                                This Week
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($filled_sales_data->take(7)->sum('total')) }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-xs font-weight-bold text-info text-uppercase">
                                This Month
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($filled_sales_data->sum('total')) }}
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="text-xs font-weight-bold text-warning text-uppercase">
                                Transactions
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                {{ $filled_sales_data->sum('count') }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-xs font-weight-bold text-primary text-uppercase">
                                Avg. Daily
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($filled_sales_data->avg('total')) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                    <a href="{{ route('dashboard.billing') }}" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    @if($recent_transactions->count() > 0)
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
                                    @foreach($recent_transactions as $transaction)
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
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No transactions yet. Start by creating hotspots and processing payments.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="withdrawModalLabel">Request Withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard.withdraw') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="withdraw_amount" class="form-label">Amount (UGX)</label>
                        <input type="number" class="form-control" id="withdraw_amount" name="amount" 
                               min="10000" max="{{ $tenant->wallet_balance }}" required>
                        <div class="form-text">Minimum: UGX 10,000 | Maximum: UGX {{ number_format($tenant->wallet_balance) }}</div>
                    </div>
                    <div class="mb-3">
                        <label for="withdraw_phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="withdraw_phone" name="phone_number" 
                               placeholder="07XXXXXXXX" required>
                        <div class="form-text">Enter the phone number where you want to receive the funds</div>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <strong>Processing Time:</strong> 24-48 hours<br>
                            <strong>Provider:</strong> MTN Mobile Money<br>
                            <strong>Fee:</strong> No additional fees
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Request Withdrawal</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Chart
var ctx = document.getElementById('salesChart').getContext('2d');
var salesChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($filled_sales_data->pluck('formatted_date')) !!},
        datasets: [{
            label: 'Daily Sales (UGX)',
            data: {!! json_encode($filled_sales_data->pluck('total')) !!},
            backgroundColor: 'rgba(78, 115, 223, 0.8)',
            borderColor: 'rgb(78, 115, 223)',
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
                        return 'Sales: UGX ' + context.parsed.y.toLocaleString();
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

// Export chart function
function exportChart(format) {
    const canvas = document.getElementById('salesChart');
    const link = document.createElement('a');
    
    if (format === 'png') {
        link.download = 'sales-chart.png';
        link.href = canvas.toDataURL('image/png');
    } else if (format === 'pdf') {
        // For PDF, you'd need a library like jsPDF
        alert('PDF export requires additional libraries. PNG export is available.');
        return;
    }
    
    link.click();
}

// Auto-update balance every 30 seconds
setInterval(function() {
    fetch('/dashboard')
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBalance = doc.querySelector('.h2.mb-0.font-weight-bold.text-gray-800');
            if (newBalance) {
                document.querySelector('.h2.mb-0.font-weight-bold.text-gray-800').textContent = newBalance.textContent;
            }
        })
        .catch(error => console.log('Auto-update failed:', error));
}, 30000);
</script>
@endpush
@endsection 
