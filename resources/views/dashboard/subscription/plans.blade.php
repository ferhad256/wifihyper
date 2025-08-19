@extends('layouts.dashboard')

@section('title', 'Subscription Plans')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('subscription.index') }}">Subscription</a></li>
                        <li class="breadcrumb-item active">Plans</li>
                    </ol>
                </div>
                <h4 class="page-title">Choose Your Plan</h4>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach($availablePlans as $plan)
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 {{ $plan['slug'] === $currentPlan->slug ? 'border-primary' : '' }}">
                @if($plan['slug'] === $currentPlan->slug)
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Current Plan</h5>
                    </div>
                @else
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ $plan['name'] }}</h5>
                    </div>
                @endif
                
                <div class="card-body d-flex flex-column">
                    <div class="text-center mb-4">
                        <h2 class="text-primary mb-2">
                            @if($plan['slug'] === 'enterprise')
                                Contact Sales
                            @else
                                {{ $plan['formatted_monthly_price'] }}
                            @endif
                        </h2>
                        <p class="text-muted">per month</p>
                        @if($plan['slug'] !== 'enterprise' && $plan['yearly_price'] > 0)
                            <small class="text-muted">or {{ $plan['formatted_yearly_price'] }}/year (save 2 months)</small>
                        @endif
                    </div>

                    <p class="card-text mb-4">{{ $plan['description'] }}</p>

                    <!-- Plan Limits -->
                    <div class="mb-4">
                        <h6 class="fw-bold">Plan Limits:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-wifi me-2"></i>
                                <strong>Hotspots:</strong>
                                @if($plan['max_hotspots'] === -1)
                                    Unlimited
                                @else
                                    Up to {{ $plan['max_hotspots'] }}
                                @endif
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-ticket-alt me-2"></i>
                                <strong>Vouchers:</strong>
                                @if($plan['max_vouchers_per_month'] === -1)
                                    Unlimited
                                @else
                                    {{ number_format($plan['max_vouchers_per_month']) }} per month
                                @endif
                            </li>
                        </ul>
                    </div>

                    <!-- Transaction Fees -->
                    <div class="mb-4">
                        <h6 class="fw-bold">Transaction Fees:</h6>
                        @if($plan['slug'] === 'enterprise')
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>No transaction charges!</strong>
                            </div>
                        @else
                            @if(is_array($plan['transaction_fees']) && count($plan['transaction_fees']) > 0)
                                <ul class="list-unstyled">
                                    @foreach($plan['transaction_fees'] as $fee)
                                        <li class="mb-1">
                                            <small>
                                                @if($fee['min'] == 0)
                                                    UGX {{ number_format($fee['min']) }} and below: {{ $fee['percentage'] }}%
                                                @elseif($fee['max'] == 999999999)
                                                    UGX {{ number_format($fee['min']) }} and above: {{ $fee['percentage'] }}%
                                                @else
                                                    UGX {{ number_format($fee['min']) }} to {{ number_format($fee['max']) }}: {{ $fee['percentage'] }}%
                                                @endif
                                            </small>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted">Transaction fees not configured</p>
                            @endif
                        @endif
                    </div>

                    <!-- Features -->
                    <div class="mb-4">
                        <h6 class="fw-bold">Key Features:</h6>
                        @if(is_array($plan['features']) && count($plan['features']) > 0)
                            <ul class="list-unstyled">
                                @foreach(array_slice($plan['features'], 0, 5) as $feature)
                                    <li class="mb-1">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <small>{{ ucwords(str_replace('_', ' ', $feature)) }}</small>
                                    </li>
                                @endforeach
                                @if(count($plan['features']) > 5)
                                    <li class="mb-1">
                                        <i class="fas fa-plus text-muted me-2"></i>
                                        <small>And {{ count($plan['features']) - 5 }} more features</small>
                                    </li>
                                @endif
                            </ul>
                        @else
                            <p class="text-muted">Features not configured</p>
                        @endif
                    </div>

                    <!-- Action Button -->
                    <div class="mt-auto">
                        @if($plan['slug'] === $currentPlan->slug)
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="fas fa-check"></i> Current Plan
                            </button>
                        @elseif($plan['slug'] === 'enterprise')
                            <button class="btn btn-warning w-100" onclick="contactSales()">
                                <i class="fas fa-phone"></i> Contact Sales
                            </button>
                        @elseif($plan['slug'] === 'pro')
                            <a href="{{ route('subscription.payment', ['plan_id' => $plan['id']]) }}" class="btn btn-primary w-100">
                                <i class="fas fa-credit-card"></i> Pay & Upgrade to Pro
                            </a>
                        @else
                            <form action="{{ route('subscription.upgrade') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan['id'] }}">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-arrow-up"></i> Upgrade to {{ $plan['name'] }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Plan Comparison -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Plan Comparison</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Feature</th>
                                    <th class="text-center">Starter</th>
                                    <th class="text-center">Pro</th>
                                    <th class="text-center">Enterprise</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Price</strong></td>
                                    <td class="text-center">Free</td>
                                    <td class="text-center">UGX 30,000/month</td>
                                    <td class="text-center">Contact Sales</td>
                                </tr>
                                <tr>
                                    <td><strong>Hotspots</strong></td>
                                    <td class="text-center">Up to 3</td>
                                    <td class="text-center">Up to 10</td>
                                    <td class="text-center">Unlimited</td>
                                </tr>
                                <tr>
                                    <td><strong>Vouchers per Month</strong></td>
                                    <td class="text-center">5,000</td>
                                    <td class="text-center">10,000</td>
                                    <td class="text-center">Unlimited</td>
                                </tr>
                                <tr>
                                    <td><strong>Transaction Fees</strong></td>
                                    <td class="text-center">15%/10%/5%</td>
                                    <td class="text-center">15%/10%/5%</td>
                                    <td class="text-center">None</td>
                                </tr>
                                <tr>
                                    <td><strong>Custom Portal</strong></td>
                                    <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                    <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                    <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                </tr>
                                <tr>
                                    <td><strong>Source Code Access</strong></td>
                                    <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                    <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                    <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                </tr>
                                <tr>
                                    <td><strong>API Access</strong></td>
                                    <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                    <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                    <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                </tr>
                                <tr>
                                    <td><strong>Priority Support</strong></td>
                                    <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                    <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                    <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function contactSales() {
    // You can implement this to open a contact form or redirect to a sales page
    alert('Please contact our sales team for Enterprise plan pricing and setup.');
    // Alternatively, you could redirect to a contact form:
    // window.location.href = '/contact-sales';
}
</script>
@endsection 