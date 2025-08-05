@extends('layouts.dashboard')

@section('title', 'Subscription Payment')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Subscription Payment</h1>
                <a href="{{ route('subscription.plans') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Plans
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Plan</h6>
                            <h4 class="text-primary">{{ $plan->name }}</h4>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted">Amount</h6>
                            <h4 class="text-success">{{ $plan->getFormattedPrice() }}</h4>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-muted">Plan Features:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-wifi me-2"></i>
                                <strong>Hotspots:</strong>
                                @if($plan->max_hotspots === -1)
                                    Unlimited
                                @else
                                    Up to {{ $plan->max_hotspots }}
                                @endif
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-ticket-alt me-2"></i>
                                <strong>Vouchers:</strong>
                                @if($plan->max_vouchers_per_month === -1)
                                    Unlimited
                                @else
                                    {{ number_format($plan->max_vouchers_per_month) }}/month
                                @endif
                            </li>
                            @if($plan->custom_portal)
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Custom portal
                                </li>
                            @endif
                            @if($plan->api_access)
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    API access
                                </li>
                            @endif
                            @if($plan->priority_support)
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Priority support
                                </li>
                            @endif
                        </ul>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Important:</strong> Your subscription will be active for 30 days from the date of successful payment. After expiration, you'll be automatically switched back to the Starter plan.
                    </div>

                    <form action="{{ route('subscription.payment.initiate') }}" method="POST">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        
                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" class="form-control @error('phone_number') is-invalid @enderror" 
                                   id="phone_number" name="phone_number" 
                                   value="{{ old('phone_number', $tenant->phone ?? '') }}" 
                                   placeholder="Enter your phone number" required>
                            <div class="form-text">This number will be used for mobile money payment</div>
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-credit-card me-2"></i>
                                Pay {{ $plan->getFormattedPrice() }} via Mobile Money
                            </button>
                        </div>
                    </form>

                    <div class="mt-4">
                        <h6 class="text-muted">Payment Method:</h6>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-mobile-alt fa-2x text-primary me-3"></i>
                            <div>
                                <strong>Mobile Money Payment</strong><br>
                                <small class="text-muted">Pay via MTN Mobile Money, Airtel Money, or other mobile money services</small>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-muted">What happens after payment?</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                You'll be redirected to your mobile money app
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Complete the payment in your mobile money app
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                You'll be automatically upgraded to {{ $plan->name }} plan
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Your subscription will be active for 30 days
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 