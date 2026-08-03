@extends('layouts.dashboard')

@section('title', 'Overview')
@section('subtitle', 'Your hotspots, sales and balance at a glance')

@section('content')

@push('styles')
<style>
    /* Wallet panel — the single most important number on the page, so it gets
       its own surface rather than competing as one tile among four. */
    .wh-wallet {
        background: var(--wh-ink-800);
        border-radius: var(--wh-r-lg);
        padding: 1.5rem;
        color: #E8EDF3;
    }
    .wh-wallet__amount {
        font-family: var(--wh-font-display);
        font-size: clamp(1.875rem, 5vw, 2.5rem);
        font-weight: 700;
        letter-spacing: -0.035em;
        color: #fff;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .wh-wallet__amount small {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--wh-signal-300);
        letter-spacing: 0;
        margin-right: 0.35rem;
    }

    /* Summary rows: label left, money right, hairline between. Reads far
       faster than five centred columns squeezed into a narrow column. */
    .wh-summary { list-style: none; margin: 0; padding: 0; }
    .wh-summary li {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.6875rem 0;
        border-bottom: 1px solid var(--wh-line);
    }
    .wh-summary li:last-child { border-bottom: 0; padding-bottom: 0; }
    .wh-summary li:first-child { padding-top: 0; }
    .wh-summary__k { font-size: 0.875rem; color: var(--wh-slate-600); }
    .wh-summary__k b { display: block; font-weight: 600; color: var(--wh-ink-900); }
    .wh-summary__k span { font-size: 0.75rem; }

    .wh-chart { position: relative; height: 20rem; width: 100%; }
    @media (max-width: 575.98px) { .wh-chart { height: 15rem; } }
</style>
@endpush

<!-- ============ Wallet + primary actions ============ -->
<div class="wh-wallet mb-3">
    <div class="row align-items-center g-3">
        <div class="col-lg-5">
            <p class="wh-eyebrow wh-eyebrow--onink mb-2">Wallet balance</p>
            <div class="wh-wallet__amount" id="walletBalance">
                <small>UGX</small>{{ number_format($tenant->wallet_balance) }}
            </div>
        </div>

        <div class="col-lg-7">
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-lg-end">
                <a href="{{ route('dashboard.billing') }}" class="btn btn-outline-light">
                    <i class="fas fa-list" aria-hidden="true"></i> Transactions
                </a>

                @if($pending_withdrawal)
                    <button class="btn btn-light" disabled>
                        <i class="fas fa-clock" aria-hidden="true"></i> Withdrawal pending
                    </button>
                @elseif($tenant->wallet_balance >= 5000 && $tenant->phone)
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                        <i class="fas fa-money-bill-wave" aria-hidden="true"></i> Request withdrawal
                    </button>
                @elseif($tenant->wallet_balance >= 5000 && !$tenant->phone)
                    <a href="{{ route('dashboard.profile') }}" class="btn btn-primary">
                        <i class="fas fa-user-pen" aria-hidden="true"></i> Add phone to withdraw
                    </a>
                @else
                    {{-- Explains why it is unavailable instead of only greying out. --}}
                    <button class="btn btn-light" disabled>
                        <i class="fas fa-lock" aria-hidden="true"></i> Withdraw at UGX 5,000
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- ============ Pending withdrawal ============ -->
@if($pending_withdrawal)
<div class="wh-panel mb-3" style="border-left: var(--wh-rule-accent) solid var(--wh-warn-600);">
    <div class="wh-panel__head">
        <h2 class="wh-panel__title">
            <i class="fas fa-clock me-2" style="color:var(--wh-warn-600);" aria-hidden="true"></i>
            Withdrawal under review
        </h2>
        <span class="wh-pill wh-pill--warn">
            {{ $pending_withdrawal->created_at->diffForHumans() }}
        </span>
    </div>
    <div class="wh-panel__body">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <span class="wh-stat__label d-block mb-1">Amount</span>
                <span class="wh-money">UGX {{ number_format($pending_withdrawal->amount) }}</span>
            </div>
            <div class="col-6 col-lg-3">
                <span class="wh-stat__label d-block mb-1">Fee (5%)</span>
                <span class="wh-money wh-money--out">UGX {{ number_format($pending_withdrawal->fee) }}</span>
            </div>
            <div class="col-6 col-lg-3">
                <span class="wh-stat__label d-block mb-1">You receive</span>
                <span class="wh-money wh-money--in">UGX {{ number_format($pending_withdrawal->net_amount) }}</span>
            </div>
            <div class="col-6 col-lg-3">
                <span class="wh-stat__label d-block mb-1">To number</span>
                <span class="wh-money">{{ $pending_withdrawal->phone_number }}</span>
            </div>
        </div>

        <hr class="my-3 wh-hairline">

        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <p class="mb-0 wh-muted" style="font-size:0.875rem;max-width:64ch;">
                Reference <span class="wh-code">{{ $pending_withdrawal->withdrawal_id }}</span> ·
                requested {{ $pending_withdrawal->created_at->format('M d, Y H:i') }}.
                An admin reviews it within 24 hours. You can't submit another request until this one is settled.
            </p>
            <a href="{{ route('dashboard.billing') }}" class="btn btn-secondary btn-sm flex-shrink-0">View details</a>
        </div>
    </div>
</div>
@endif

<!-- ============ Stats ============ -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="wh-stat wh-stat--signal">
            <div class="wh-stat__head">
                <span class="wh-stat__label">Available vouchers</span>
                <span class="wh-stat__icon"><i class="fas fa-ticket-alt" aria-hidden="true"></i></span>
            </div>
            <span class="wh-stat__value">{{ number_format($stats['unused_vouchers']) }}</span>
            <p class="wh-stat__meta">
                @if($stats['unused_vouchers'] == 0)
                    <span class="wh-pill wh-pill--crit">Out of stock</span>
                @elseif($stats['unused_vouchers'] < 20)
                    <span class="wh-pill wh-pill--warn">Running low</span>
                @else
                    Ready to sell
                @endif
                <a href="{{ route('vouchers.index') }}" class="ms-1">Manage</a>
            </p>
        </div>
    </div>

    <div class="col-md-6">
        <div class="wh-stat wh-stat--value">
            <div class="wh-stat__head">
                <span class="wh-stat__label">Active hotspots</span>
                <span class="wh-stat__icon"><i class="fas fa-tower-broadcast" aria-hidden="true"></i></span>
            </div>
            <span class="wh-stat__value">{{ number_format($stats['total_hotspots']) }}</span>
            <p class="wh-stat__meta">
                Sites you're currently selling from
                <a href="{{ route('dashboard.hotspots') }}" class="ms-1">Manage</a>
            </p>
        </div>
    </div>
</div>

<!-- ============ Chart + summary ============ -->
<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="wh-panel h-100">
            <div class="wh-panel__head">
                <h2 class="wh-panel__title">Sales overview · {{ now()->year }}</h2>
                <div class="dropdown">
                    <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download" aria-hidden="true"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item" onclick="exportChart('png')">PNG image</button></li>
                        <li><a class="dropdown-item" href="{{ route('transactions.export') }}">Transactions CSV</a></li>
                    </ul>
                </div>
            </div>
            <div class="wh-panel__body">
                <div class="wh-chart">
                    <canvas id="salesChart"
                            aria-label="Monthly sales for {{ now()->year }} in Ugandan shillings"
                            role="img"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="wh-panel h-100">
            <div class="wh-panel__head">
                <h2 class="wh-panel__title">Takings</h2>
            </div>
            <div class="wh-panel__body">
                <ul class="wh-summary">
                    <li>
                        <span class="wh-summary__k">
                            <b>Today</b>
                            <span>since {{ $sales_summary['today']['start_date'] }}</span>
                        </span>
                        <span class="wh-money">
                            <span class="wh-money__cur">UGX</span>{{ number_format($sales_summary['today']['amount']) }}
                        </span>
                    </li>
                    <li>
                        <span class="wh-summary__k">
                            <b>Yesterday</b>
                            <span>{{ $sales_summary['yesterday']['start_date'] }}</span>
                        </span>
                        <span class="wh-money">
                            <span class="wh-money__cur">UGX</span>{{ number_format($sales_summary['yesterday']['amount']) }}
                        </span>
                    </li>
                    <li>
                        <span class="wh-summary__k">
                            <b>This week</b>
                            <span>since {{ $sales_summary['this_week']['start_date'] }}</span>
                        </span>
                        <span class="wh-money">
                            <span class="wh-money__cur">UGX</span>{{ number_format($sales_summary['this_week']['amount']) }}
                        </span>
                    </li>
                    <li>
                        <span class="wh-summary__k">
                            <b>This month</b>
                            <span>since {{ $sales_summary['this_month']['start_date'] }}</span>
                        </span>
                        <span class="wh-money">
                            <span class="wh-money__cur">UGX</span>{{ number_format($sales_summary['this_month']['amount']) }}
                        </span>
                    </li>
                    <li>
                        <span class="wh-summary__k">
                            <b>This year</b>
                            <span>since {{ $sales_summary['this_year']['start_date'] }}</span>
                        </span>
                        <span class="wh-money">
                            <span class="wh-money__cur">UGX</span>{{ number_format($sales_summary['this_year']['amount']) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ============ Recent transactions ============ -->
<div class="wh-panel">
    <div class="wh-panel__head">
        <h2 class="wh-panel__title">Recent transactions</h2>
        <a href="{{ route('dashboard.billing') }}" class="btn btn-secondary btn-sm">View all</a>
    </div>

    @if($recent_transactions->count() > 0)
        <div class="wh-scroll-x">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Hotspot</th>
                        <th scope="col">Package</th>
                        <th scope="col">Voucher</th>
                        <th scope="col" class="text-end">Amount</th>
                        <th scope="col" class="text-end">Fee</th>
                        <th scope="col" class="text-end">Net</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent_transactions as $transaction)
                    <tr>
                        <td class="text-nowrap">
                            {{ $transaction->created_at->format('M d') }}
                            <span class="wh-muted d-block" style="font-size:0.75rem;">
                                {{ $transaction->created_at->format('H:i') }}
                            </span>
                        </td>
                        <td><span class="wh-code">{{ $transaction->transaction_id }}</span></td>
                        <td>{{ $transaction->hotspot->name ?? '—' }}</td>
                        <td>{{ $transaction->package->name ?? '—' }}</td>
                        <td>
                            @if($transaction->voucher)
                                <span class="wh-code">{{ $transaction->voucher->code }}</span>
                            @else
                                <span class="wh-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="wh-money">{{ number_format($transaction->amount) }}</span>
                            <span class="wh-muted d-block" style="font-size:0.75rem;">
                                {{ $transaction->fee_percentage }}% fee
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="wh-money wh-money--out">{{ number_format($transaction->transaction_fee) }}</span>
                        </td>
                        <td class="text-end">
                            <span class="wh-money wh-money--in">{{ number_format($transaction->net_amount) }}</span>
                        </td>
                        <td class="text-nowrap">{{ $transaction->phone_number ?? '—' }}</td>
                        <td>{!! $transaction->status_badge !!}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="wh-empty">
            <div class="wh-empty__icon"><i class="fas fa-inbox" aria-hidden="true"></i></div>
            <p class="wh-empty__title">No sales yet</p>
            <p class="wh-empty__text">
                Once you've added a hotspot and uploaded vouchers, every sale will appear here
                with its fee and net amount.
            </p>
            <a href="{{ route('dashboard.hotspots') }}" class="btn btn-primary">Add your first hotspot</a>
        </div>
    @endif
</div>

<!-- ============ Withdraw modal ============ -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="withdrawModalLabel">Request a withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard.withdraw') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="withdraw_amount" class="form-label">
                            Amount to withdraw <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">UGX</span>
                            <input type="number" class="form-control" id="withdraw_amount" name="amount"
                                   min="5000" max="{{ $tenant->wallet_balance }}" step="1" required
                                   inputmode="numeric" oninput="calculateWithdrawalFee()">
                        </div>
                        <div class="form-text">
                            Minimum UGX 5,000 · available UGX {{ number_format($tenant->wallet_balance) }}
                        </div>
                    </div>

                    <!-- Live breakdown so the deduction is visible before submitting -->
                    <div class="wh-panel mb-3" style="background:var(--wh-paper-sunken);">
                        <div class="wh-panel__body py-2">
                            <div class="d-flex justify-content-between align-items-baseline py-1">
                                <span class="wh-summary__k">Withdrawal fee (5%)</span>
                                <span class="wh-money wh-money--out" id="fee_display">UGX 0</span>
                            </div>
                            <hr class="my-2 wh-hairline">
                            <div class="d-flex justify-content-between align-items-baseline py-1">
                                <span class="wh-summary__k"><b>You will receive</b></span>
                                <span class="wh-money wh-money--in" id="receive_display" style="font-size:1.125rem;">UGX 0</span>
                            </div>
                            {{-- Kept for the existing script contract; the value duplicates
                                 "you will receive" so it is not shown twice. --}}
                            <span id="net_display" class="d-none">UGX 0</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="withdraw_phone" class="form-label">Mobile money number</label>
                        <input type="tel" class="form-control" id="withdraw_phone" name="phone_number"
                               value="{{ $tenant->phone }}" readonly required autocomplete="tel">
                        <div class="form-text">
                            <i class="fas fa-lock me-1" aria-hidden="true"></i>
                            For security, payouts only go to the number registered on your account.
                            @if(!$tenant->phone)
                                <span class="text-danger d-block mt-1">
                                    Add a phone number to your profile before withdrawing.
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="alert alert-info mb-0">
                        <strong class="d-block mb-1">What happens next</strong>
                        You submit the request, an admin reviews it, then the funds are sent to your
                        mobile money account — usually within 24 hours.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" {{ !$tenant->phone ? 'disabled' : '' }}>
                        {{ $tenant->phone ? 'Request withdrawal' : 'Update profile first' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ---- Sales chart ----------------------------------------------------------
// Colours are read from the brand tokens so the chart follows the theme
// instead of carrying its own hard-coded palette.
(function () {
    var css    = getComputedStyle(document.documentElement);
    var signal = css.getPropertyValue('--wh-signal-600').trim() || '#0A7A6D';
    var line   = css.getPropertyValue('--wh-line').trim() || '#DCE2DC';
    var muted  = css.getPropertyValue('--wh-slate-600').trim() || '#48586E';

    new Chart(document.getElementById('salesChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($filled_sales_data->pluck('formatted_date')) !!},
            datasets: [{
                label: 'Sales (UGX)',
                data: {!! json_encode($filled_sales_data->pluck('total')) !!},
                backgroundColor: signal,
                hoverBackgroundColor: signal,
                borderRadius: 3,
                borderSkipped: false,
                maxBarThickness: 44
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // Respect the reader's motion preference rather than always animating in.
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0A1628',
                    padding: 10,
                    cornerRadius: 6,
                    displayColors: false,
                    callbacks: {
                        label: function (context) {
                            return 'UGX ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: line, drawTicks: false },
                    ticks: {
                        color: muted,
                        padding: 8,
                        callback: function (value) { return value.toLocaleString(); }
                    }
                },
                x: {
                    border: { color: line },
                    grid: { display: false },
                    ticks: { color: muted, autoSkip: true, maxRotation: 0 }
                }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });
})();

function exportChart(format) {
    if (format !== 'png') return;
    var canvas = document.getElementById('salesChart');
    var link = document.createElement('a');
    link.download = 'sales-chart.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// ---- Live balance ---------------------------------------------------------
// Targets a stable id rather than a chain of layout classes, so restyling the
// balance no longer silently breaks the refresh.
setInterval(function () {
    fetch('{{ route('dashboard') }}')
        .then(response => response.text())
        .then(html => {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var next = doc.getElementById('walletBalance');
            var current = document.getElementById('walletBalance');
            if (next && current) current.innerHTML = next.innerHTML;
        })
        .catch(error => console.log('Auto-update failed:', error));
}, 30000);

// ---- Withdrawal breakdown -------------------------------------------------
function calculateWithdrawalFee() {
    var amount = parseFloat(document.getElementById('withdraw_amount').value) || 0;
    var fee = 0, net = 0;

    if (amount >= 5000) {
        fee = Math.round(amount * 0.05);
        net = Math.round(amount - fee);
    }

    document.getElementById('fee_display').textContent     = 'UGX ' + fee.toLocaleString();
    document.getElementById('net_display').textContent     = 'UGX ' + net.toLocaleString();
    document.getElementById('receive_display').textContent = 'UGX ' + net.toLocaleString();
}

document.addEventListener('DOMContentLoaded', calculateWithdrawalFee);
</script>
@endpush
@endsection
