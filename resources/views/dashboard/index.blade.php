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
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-info btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-headset me-2"></i>Support
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="https://wa.me/256704791624?text=Hello! I need help with my WIFIHYPER dashboard" target="_blank">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp: +256704791624
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="https://wa.me/256783052764?text=Hello! I need help with my WIFIHYPER dashboard" target="_blank">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp: +256783052764
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="tel:0392998816">
                            <i class="fas fa-phone me-2"></i>Call: 0392998816
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="tel:0783052764">
                            <i class="fas fa-phone me-2"></i>Call: 0783052764
                        </a>
                    </li>
                </ul>
            </div>
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
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="{{ route('dashboard.billing') }}" class="btn btn-info btn-lg">
                                    <i class="fas fa-list me-2"></i>View Transactions
                                </a>
                                @if($tenant->wallet_balance >= 5000 && $tenant->phone)
                                    <button class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                        <i class="fas fa-money-bill-wave me-2"></i>Request Withdrawal
                                    </button>
                                @elseif($tenant->wallet_balance >= 5000 && !$tenant->phone)
                                    <a href="{{ route('dashboard.profile') }}" class="btn btn-outline-warning btn-lg">
                                        <i class="fas fa-user-edit me-2"></i>Add Phone Number to Withdraw
                                    </a>
                                @else
                                    <button class="btn btn-outline-secondary btn-lg" disabled title="Minimum withdrawal amount is UGX 5,000">
                                        <i class="fas fa-lock me-2"></i>Withdrawal Locked
                                        <br><small>Min. UGX 5,000</small>
                                    </button>
                                @endif
                            </div>
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
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-info btn-sm dropdown-toggle w-100" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-headset me-2"></i>Get Support
                            </button>
                            <ul class="dropdown-menu w-100">
                                <li>
                                    <a class="dropdown-item" href="https://wa.me/256704791624?text=Hello! I need help with my WIFIHYPER dashboard" target="_blank">
                                        <i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp Support 1
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="https://wa.me/256783052764?text=Hello! I need help with my WIFIHYPER dashboard" target="_blank">
                                        <i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp Support 2
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="tel:0392998816">
                                        <i class="fas fa-phone me-2 text-primary"></i>0392998816
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="tel:0783052764">
                                        <i class="fas fa-phone me-2 text-primary"></i>0783052764
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            <!-- Sales Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sales Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <div class="text-xs font-weight-bold text-primary text-uppercase">
                                Today
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($sales_summary['today']['amount']) }}
                            </div>
                            <div class="text-xs text-muted">
                                Since {{ $sales_summary['today']['start_date'] }}
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <div class="text-xs font-weight-bold text-success text-uppercase">
                                This Week
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($sales_summary['this_week']['amount']) }}
                            </div>
                            <div class="text-xs text-muted">
                                Since {{ $sales_summary['this_week']['start_date'] }}
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <div class="text-xs font-weight-bold text-info text-uppercase">
                                This Month
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($sales_summary['this_month']['amount']) }}
                            </div>
                            <div class="text-xs text-muted">
                                Since {{ $sales_summary['this_month']['start_date'] }}
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase">
                                This Year
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                UGX {{ number_format($sales_summary['this_year']['amount']) }}
                            </div>
                            <div class="text-xs text-muted">
                                Since {{ $sales_summary['this_year']['start_date'] }}
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
                        <label for="withdraw_amount" class="form-label">Withdrawal Amount (UGX)</label>
                        <input type="number" class="form-control" id="withdraw_amount" name="amount" 
                               min="5000" max="{{ $tenant->wallet_balance }}" required onchange="calculateWithdrawalFee()">
                        <div class="form-text">Minimum: UGX 5,000 | Maximum: UGX {{ number_format($tenant->wallet_balance) }}</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-4">
                                <label class="form-label text-muted">Withdrawal Fee (5%)</label>
                                <div class="h6 text-warning" id="fee_display">UGX 0</div>
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted">Net Amount</label>
                                <div class="h6 text-success" id="net_display">UGX 0</div>
                            </div>
                            <div class="col-4">
                                <label class="form-label text-muted">You Will Receive</label>
                                <div class="h5 text-primary fw-bold" id="receive_display">UGX 0</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="withdraw_phone" class="form-label">Phone Number (Registered Contact)</label>
                        <input type="tel" class="form-control" id="withdraw_phone" name="phone_number" 
                               value="{{ $tenant->phone }}" readonly required>
                        <div class="form-text">
                            <i class="fas fa-lock me-1"></i>
                            Withdrawals can only be made to your registered phone number for security purposes.
                            @if(!$tenant->phone)
                                <span class="text-danger">Please update your profile with a phone number to make withdrawals.</span>
                            @endif
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="fas fa-info-circle me-2"></i>Withdrawal Request Process
                        </h6>
                        <ul class="mb-2">
                            <li><strong>Step 1:</strong> Submit withdrawal request</li>
                            <li><strong>Step 2:</strong> Admin reviews and approves request</li>
                            <li><strong>Step 3:</strong> Funds sent to your mobile money account</li>
                            <li><strong>Processing Time:</strong> Within 24 hours</li>
                        </ul>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Fee Notice:</strong> A 5% transaction fee will be deducted from your withdrawal amount.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" {{ !$tenant->phone ? 'disabled' : '' }}>
                        @if($tenant->phone)
                            Request Withdrawal
                        @else
                            Update Profile First
                        @endif
                    </button>
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

// Calculate withdrawal fee in real-time
function calculateWithdrawalFee() {
    const amountInput = document.getElementById('withdraw_amount');
    const amount = parseFloat(amountInput.value) || 0;
    
    if (amount >= 5000) {
        const fee = amount * 0.05; // 5% fee
        const netAmount = amount - fee;
        
        document.getElementById('fee_display').textContent = 'UGX ' + Math.round(fee).toLocaleString();
        document.getElementById('net_display').textContent = 'UGX ' + Math.round(netAmount).toLocaleString();
        document.getElementById('receive_display').textContent = 'UGX ' + Math.round(netAmount).toLocaleString();
    } else {
        document.getElementById('fee_display').textContent = 'UGX 0';
        document.getElementById('net_display').textContent = 'UGX 0';
        document.getElementById('receive_display').textContent = 'UGX 0';
    }
}

// Calculate fee on page load if there's a value
document.addEventListener('DOMContentLoaded', function() {
    calculateWithdrawalFee();
});
</script>
@endpush
@endsection 
