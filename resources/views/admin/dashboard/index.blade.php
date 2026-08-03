@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Platform activity as of ' . now()->format('M d, Y · H:i'))

@section('content')

@php
    $pendingCount = $stats['pending_withdrawals'];
    $avgFee = $stats['total_transactions'] > 0
        ? $stats['total_fees'] / $stats['total_transactions']
        : 0;
@endphp

<!-- ============ Platform stats ============
     Pending withdrawals leads because it is the only tile that represents
     work waiting on an admin. -->
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="wh-stat {{ $pendingCount > 0 ? 'wh-stat--crit' : 'wh-stat--ok' }}">
            <div class="wh-stat__head">
                <span class="wh-stat__label">Pending withdrawals</span>
                <span class="wh-stat__icon">
                    <i class="fas {{ $pendingCount > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check' }}" aria-hidden="true"></i>
                </span>
            </div>
            <span class="wh-stat__value">{{ number_format($pendingCount) }}</span>
            <p class="wh-stat__meta">
                @if($pendingCount > 0)
                    <span class="wh-pill wh-pill--crit">Needs review</span>
                    <span class="d-block mt-1">UGX {{ number_format($stats['pending_withdrawal_amount']) }} queued</span>
                @else
                    Nothing waiting on you
                @endif
            </p>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="wh-stat wh-stat--signal">
            <div class="wh-stat__head">
                <span class="wh-stat__label">Tenants</span>
                <span class="wh-stat__icon"><i class="fas fa-users" aria-hidden="true"></i></span>
            </div>
            <span class="wh-stat__value">{{ number_format($stats['total_tenants']) }}</span>
            <p class="wh-stat__meta">{{ number_format($stats['active_tenants']) }} active</p>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="wh-stat wh-stat--value">
            <div class="wh-stat__head">
                <span class="wh-stat__label">Tenant funds</span>
                <span class="wh-stat__icon"><i class="fas fa-vault" aria-hidden="true"></i></span>
            </div>
            <span class="wh-stat__value"><small>UGX </small>{{ number_format($stats['total_revenue']) }}</span>
            <p class="wh-stat__meta">{{ number_format($stats['total_transactions']) }} transactions</p>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="wh-stat wh-stat--ok">
            <div class="wh-stat__head">
                <span class="wh-stat__label">Fees today</span>
                <span class="wh-stat__icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>
            </div>
            <span class="wh-stat__value"><small>UGX </small>{{ number_format($stats['today_fees']) }}</span>
            <p class="wh-stat__meta">Platform earnings so far today</p>
        </div>
    </div>
</div>

<!-- ============ Pending withdrawals ============ -->
<div class="wh-panel mb-3">
    <div class="wh-panel__head">
        <h2 class="wh-panel__title">
            Pending withdrawals
            @if($pendingCount > 0)
                <span class="wh-pill wh-pill--crit ms-2">{{ $pendingCount }}</span>
            @endif
        </h2>
        <a href="{{ route('admin.withdrawals') }}" class="btn btn-secondary btn-sm">View all</a>
    </div>

    @if($pending_withdrawals->count() > 0)
        <div class="wh-scroll-x">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col">Tenant</th>
                        <th scope="col" class="text-end">Amount</th>
                        <th scope="col">Payout number</th>
                        <th scope="col">Requested</th>
                        <th scope="col" class="text-end">Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending_withdrawals as $withdrawal)
                    <tr>
                        <td>
                            <span class="fw-semibold d-block">{{ $withdrawal->tenant->name }}</span>
                            <span class="wh-muted" style="font-size:0.75rem;">{{ $withdrawal->tenant->email }}</span>
                        </td>
                        <td class="text-end">
                            <span class="wh-money">UGX {{ number_format($withdrawal->amount) }}</span>
                        </td>
                        <td class="text-nowrap">{{ $withdrawal->phone_number }}</td>
                        <td class="text-nowrap">
                            {{ $withdrawal->created_at->format('M d, H:i') }}
                            <span class="wh-muted d-block" style="font-size:0.75rem;">
                                {{ $withdrawal->created_at->diffForHumans() }}
                            </span>
                        </td>
                        <td class="text-end">
                            {{-- Labelled rather than icon-only: these two actions move
                                 real money and must not be told apart by shape alone. --}}
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-success btn-sm"
                                        onclick="approveWithdrawal({{ $withdrawal->id }})">
                                    Approve
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="rejectWithdrawal({{ $withdrawal->id }})">
                                    Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="wh-empty">
            <div class="wh-empty__icon" style="background:var(--wh-ok-050);color:var(--wh-ok-600);">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
            </div>
            <p class="wh-empty__title">Queue is clear</p>
            <p class="wh-empty__text">No withdrawal requests are waiting for a decision.</p>
        </div>
    @endif
</div>

<!-- ============ Fee analytics ============ -->
<div class="wh-panel mb-3">
    <div class="wh-panel__head">
        <h2 class="wh-panel__title">Platform earnings</h2>
        <span class="wh-muted" style="font-size:0.8125rem;">Transaction fees + withdrawal fees</span>
    </div>
    <div class="wh-panel__body">
        <div class="row g-3">
            <div class="col-lg-3 col-6">
                <span class="wh-stat__label d-block mb-1">All time</span>
                <span class="wh-stat__value" style="font-size:1.375rem;">
                    <small>UGX </small>{{ number_format($stats['total_fees']) }}
                </span>
            </div>
            <div class="col-lg-3 col-6">
                <span class="wh-stat__label d-block mb-1">This week</span>
                <span class="wh-stat__value" style="font-size:1.375rem;">
                    <small>UGX </small>{{ number_format($stats['this_week_fees']) }}
                </span>
            </div>
            <div class="col-lg-3 col-6">
                <span class="wh-stat__label d-block mb-1">This month</span>
                <span class="wh-stat__value" style="font-size:1.375rem;">
                    <small>UGX </small>{{ number_format($stats['this_month_fees']) }}
                </span>
            </div>
            <div class="col-lg-3 col-6">
                <span class="wh-stat__label d-block mb-1">Average per transaction</span>
                <span class="wh-stat__value" style="font-size:1.375rem;">
                    <small>UGX </small>{{ number_format($avgFee) }}
                </span>
            </div>
        </div>

        <hr class="my-4 wh-hairline">

        <p class="wh-eyebrow mb-3">Monthly fees · {{ now()->year }}</p>
        <div class="wh-chart">
            <canvas id="feeChart" role="img"
                    aria-label="Monthly platform fees for {{ now()->year }} in Ugandan shillings"></canvas>
        </div>
    </div>
</div>

<!-- ============ SMS usage ============ -->
<div class="wh-panel mb-3">
    <div class="wh-panel__head">
        <h2 class="wh-panel__title">SMS usage</h2>
        <span class="wh-pill wh-pill--info">{{ number_format($sms_today ?? 0) }} sent today</span>
    </div>
    @php($smsTotal = collect($sms_daily ?? [])->sum('count'))

    @if($smsTotal > 0)
        <div class="wh-panel__body">
            <p class="wh-eyebrow mb-3">Last 14 days</p>
            <div class="wh-chart" style="height:16rem;">
                <canvas id="smsChart" role="img" aria-label="SMS messages sent per day over the last 14 days"></canvas>
            </div>
        </div>
    @else
        {{-- A flat line along zero says less than saying nothing was sent. --}}
        <div class="wh-empty">
            <div class="wh-empty__icon"><i class="fas fa-comment-sms" aria-hidden="true"></i></div>
            <p class="wh-empty__title">No SMS sent in the last 14 days</p>
            <p class="wh-empty__text">Voucher codes go out by SMS as sales complete, so this fills in once tenants start selling.</p>
        </div>
    @endif
</div>

<!-- ============ Recent tenants ============ -->
{{-- 5/7 rather than 6/6: the transactions table carries five columns and gets
     clipped in a half-width panel on a 1440px screen. --}}
<div class="row g-3 mb-3">
    <div class="col-xl-5 col-lg-12">
        <div class="wh-panel h-100">
            <div class="wh-panel__head">
                <h2 class="wh-panel__title">Recent tenants</h2>
                <a href="{{ route('admin.tenants') }}" class="btn btn-secondary btn-sm">View all</a>
            </div>

            @if($recent_tenants->count() > 0)
                <div class="wh-scroll-x">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Status</th>
                                <th scope="col">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent_tenants as $tenant)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="fw-semibold d-block">
                                        {{ $tenant->name }}
                                    </a>
                                    <span class="wh-muted" style="font-size:0.75rem;">{{ $tenant->email }}</span>
                                </td>
                                <td>
                                    @if($tenant->is_active)
                                        <span class="wh-pill wh-pill--ok">
                                            <span class="wh-dot wh-dot--ok"></span> Active
                                        </span>
                                    @else
                                        <span class="wh-pill">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $tenant->created_at->format('M d, Y') }}
                                    <span class="wh-muted d-block" style="font-size:0.75rem;">
                                        {{ $tenant->created_at->diffForHumans() }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="wh-empty">
                    <div class="wh-empty__icon"><i class="fas fa-users" aria-hidden="true"></i></div>
                    <p class="wh-empty__title">No tenants yet</p>
                    <p class="wh-empty__text">Accounts will appear here as operators sign up.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- ============ Recent transactions ============ -->
    <div class="col-xl-7 col-lg-12">
        <div class="wh-panel h-100">
            <div class="wh-panel__head">
                <h2 class="wh-panel__title">Recent transactions</h2>
                <a href="{{ route('admin.transactions') }}" class="btn btn-secondary btn-sm">View all</a>
            </div>

            @if($recent_transactions->count() > 0)
                <div class="wh-scroll-x">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Tenant</th>
                                <th scope="col">Hotspot</th>
                                <th scope="col" class="text-end">Amount</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent_transactions as $transaction)
                            <tr>
                                <td class="text-nowrap">{{ $transaction->created_at->format('M d, H:i') }}</td>
                                <td>
                                    <span class="fw-semibold d-block">{{ $transaction->tenant->name }}</span>
                                    <span class="wh-muted" style="font-size:0.75rem;">{{ $transaction->tenant->email }}</span>
                                </td>
                                <td>
                                    {{ $transaction->hotspot->name ?? '—' }}
                                    <span class="wh-muted d-block" style="font-size:0.75rem;">
                                        {{ $transaction->package->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="wh-money">UGX {{ number_format($transaction->amount) }}</span>
                                </td>
                                <td>{!! $transaction->status_badge !!}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="wh-empty">
                    <div class="wh-empty__icon"><i class="fas fa-right-left" aria-hidden="true"></i></div>
                    <p class="wh-empty__title">No transactions</p>
                    <p class="wh-empty__text">Sales across all tenants will show up here.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ============ Approve modal ============ -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalLabel">Approve withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="approvalForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">
                            Notes <span class="wh-muted fw-normal">(optional)</span>
                        </label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3"
                                  placeholder="Anything worth recording about this approval…"></textarea>
                    </div>
                    <div class="alert alert-info mb-0">
                        Approving deducts the amount from the tenant's wallet balance and sends
                        the payout to their registered number.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve withdrawal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ Reject modal ============ -->
<div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectionModalLabel">Reject withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectionForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reject_notes" class="form-label">
                            Reason for rejection <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="reject_notes" name="admin_notes" rows="3"
                                  placeholder="Explain why this request is being turned down…" required></textarea>
                        <div class="form-text">The tenant is emailed this reason, so keep it clear.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject withdrawal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    // Chart colours come from the brand tokens rather than being hard-coded,
    // so both charts stay in step with the rest of the panel.
    var css    = getComputedStyle(document.documentElement);
    var signal = css.getPropertyValue('--wh-signal-600').trim() || '#0A7A6D';
    var line   = css.getPropertyValue('--wh-line').trim() || '#DCE2DC';
    var muted  = css.getPropertyValue('--wh-slate-600').trim() || '#48586E';
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var baseScales = {
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: line, drawTicks: false },
            ticks: { color: muted, padding: 8, callback: function (v) { return v.toLocaleString(); } }
        },
        x: {
            border: { color: line },
            grid: { display: false },
            ticks: { color: muted, autoSkip: true, maxRotation: 0 }
        }
    };

    var baseTooltip = {
        backgroundColor: '#060D18',
        padding: 10,
        cornerRadius: 6,
        displayColors: false
    };

    // ---- Monthly fees ----
    new Chart(document.getElementById('feeChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($filled_fee_data->pluck('formatted_date')) !!},
            datasets: [{
                label: 'Fees (UGX)',
                data: {!! json_encode($filled_fee_data->pluck('total_fees')) !!},
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
            animation: reduce ? false : { duration: 400 },
            plugins: {
                legend: { display: false },
                tooltip: Object.assign({}, baseTooltip, {
                    callbacks: {
                        label: function (c) { return 'UGX ' + c.parsed.y.toLocaleString(); }
                    }
                })
            },
            scales: baseScales,
            interaction: { intersect: false, mode: 'index' }
        }
    });

    // ---- Daily SMS ----
    // The canvas is only rendered when there is something to plot; an empty
    // state replaces it otherwise.
    var smsCanvas = document.getElementById('smsChart');
    if (!smsCanvas) return;

    new Chart(smsCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: {!! json_encode(collect($sms_daily ?? [])->pluck('date')) !!},
            datasets: [{
                label: 'SMS sent',
                data: {!! json_encode(collect($sms_daily ?? [])->pluck('count')) !!},
                borderColor: signal,
                backgroundColor: 'rgba(10, 122, 109, 0.10)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 2,
                pointHoverRadius: 5,
                pointBackgroundColor: signal
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduce ? false : { duration: 400 },
            plugins: {
                legend: { display: false },
                tooltip: Object.assign({}, baseTooltip, {
                    callbacks: {
                        label: function (c) { return c.parsed.y.toLocaleString() + ' SMS'; }
                    }
                })
            },
            scales: {
                y: Object.assign({}, baseScales.y, {
                    ticks: Object.assign({}, baseScales.y.ticks, { precision: 0 })
                }),
                x: baseScales.x
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });
})();

function approveWithdrawal(id) {
    document.getElementById('approvalForm').action = '/admin/withdrawals/' + id + '/approve';
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function rejectWithdrawal(id) {
    document.getElementById('rejectionForm').action = '/admin/withdrawals/' + id + '/reject';
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}
</script>
@endpush
