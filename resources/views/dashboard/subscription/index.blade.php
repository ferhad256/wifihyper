@extends('layouts.dashboard')

@section('title', 'Subscription')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Subscription</h1>
                <a href="{{ route('subscription.plans') }}" class="btn btn-primary">
                    <i class="fas fa-arrow-up"></i> Upgrade Plan
                </a>
            </div>
        </div>
    </div>

    <!-- Current Plan -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Current Plan</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="text-primary">{{ $currentPlan->name }}</h4>
                            <p class="text-muted">{{ $currentPlan->description }}</p>
                            <div class="mb-3">
                                <span class="badge bg-primary fs-6">{{ $currentPlan->getFormattedPrice() }}</span>
                                @if($currentPlan->slug === 'enterprise')
                                    <span class="badge bg-warning ms-2">Custom Pricing</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="mb-2">
                                <strong>Subscription Status:</strong>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="mb-2">
                                <strong>Expires:</strong>
                                {{ $tenant->subscription_expires_at ? $tenant->subscription_expires_at->format('M d, Y') : 'Never' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-wifi fa-2x text-primary"></i>
                    </div>
                    <h4 class="card-title">{{ $planUsage['usage']['hotspots']['current'] }}/{{ $planUsage['usage']['hotspots']['max'] === -1 ? '∞' : $planUsage['usage']['hotspots']['max'] }}</h4>
                    <p class="card-text">Hotspots</p>
                    @if($planUsage['usage']['hotspots']['remaining'] > 0)
                        <span class="badge bg-success">{{ $planUsage['usage']['hotspots']['remaining'] }} remaining</span>
                    @elseif($planUsage['usage']['hotspots']['max'] === -1)
                        <span class="badge bg-info">Unlimited</span>
                    @else
                        <span class="badge bg-warning">Limit reached</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-ticket-alt fa-2x text-success"></i>
                    </div>
                    <h4 class="card-title">{{ $planUsage['usage']['vouchers']['current_month'] }}/{{ $planUsage['usage']['vouchers']['max_per_month'] === -1 ? '∞' : $planUsage['usage']['vouchers']['max_per_month'] }}</h4>
                    <p class="card-text">Vouchers (This Month)</p>
                    @if($planUsage['usage']['vouchers']['remaining'] > 0)
                        <span class="badge bg-success">{{ $planUsage['usage']['vouchers']['remaining'] }} remaining</span>
                    @elseif($planUsage['usage']['vouchers']['max_per_month'] === -1)
                        <span class="badge bg-info">Unlimited</span>
                    @else
                        <span class="badge bg-warning">Monthly limit reached</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-credit-card fa-2x text-info"></i>
                    </div>
                    <h4 class="card-title">{{ $planUsage['usage']['transactions']['current_month'] }}/{{ $planUsage['usage']['transactions']['max_per_month'] === -1 ? '∞' : $planUsage['usage']['transactions']['max_per_month'] }}</h4>
                    <p class="card-text">Transactions (This Month)</p>
                    @if($planUsage['usage']['transactions']['remaining'] > 0)
                        <span class="badge bg-success">{{ $planUsage['usage']['transactions']['remaining'] }} remaining</span>
                    @elseif($planUsage['usage']['transactions']['max_per_month'] === -1)
                        <span class="badge bg-info">Unlimited</span>
                    @else
                        <span class="badge bg-warning">Monthly limit reached</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Fees -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Transaction Fees</h5>
                </div>
                <div class="card-body">
                    @if($currentPlan->slug === 'enterprise')
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Enterprise Plan:</strong> No transaction charges apply to your account.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Amount Range</th>
                                        <th>Fee Percentage</th>
                                        <th>Example</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($planUsage['transaction_fees'] as $fee)
                                        <tr>
                                            <td>
                                                @if($fee['min'] == 0)
                                                    UGX {{ number_format($fee['min']) }} and below
                                                @elseif($fee['max'] == 999999999)
                                                    UGX {{ number_format($fee['min']) }} and above
                                                @else
                                                    UGX {{ number_format($fee['min']) }} to {{ number_format($fee['max']) }}
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">{{ $fee['percentage'] }}%</span>
                                            </td>
                                            <td>
                                                @php
                                                    $exampleAmount = $fee['min'] > 0 ? $fee['min'] : 1000;
                                                    $exampleFee = ($exampleAmount * $fee['percentage']) / 100;
                                                    $exampleTotal = $exampleAmount + $exampleFee;
                                                @endphp
                                                UGX {{ number_format($exampleAmount) }} + {{ $fee['percentage'] }}% = UGX {{ number_format($exampleTotal) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Plan Features -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Features</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        @foreach($planUsage['features'] as $feature)
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                {{ ucwords(str_replace('_', ' ', $feature)) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Restrictions</h5>
                </div>
                <div class="card-body">
                    @if(empty($planUsage['restrictions']) || in_array('none', $planUsage['restrictions']))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            No restrictions on this plan!
                        </div>
                    @else
                        <ul class="list-unstyled">
                            @foreach($planUsage['restrictions'] as $restriction)
                                <li class="mb-2">
                                    <i class="fas fa-times text-danger me-2"></i>
                                    {{ ucwords(str_replace('_', ' ', $restriction)) }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 