@extends('layouts.dashboard')

@section('title', 'Settings')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Settings</h1>
    </div>

    <div class="row">
        <!-- Payment Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Gateway Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.payment') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="payment_gateway" class="form-label">Payment Gateway</label>
                            <select class="form-select" id="payment_gateway" name="payment_gateway">
                                <option value="yo_payments" {{ $tenant->payment_gateway === 'yo_payments' ? 'selected' : '' }}>Yo Payments</option>
                                <option value="stripe" {{ $tenant->payment_gateway === 'stripe' ? 'selected' : '' }}>Stripe</option>
                                <option value="paypal" {{ $tenant->payment_gateway === 'paypal' ? 'selected' : '' }}>PayPal</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="yo_api_key" class="form-label">Yo Payments API Key</label>
                            <input type="text" class="form-control" id="yo_api_key" name="yo_api_key" 
                                   value="{{ old('yo_api_key') }}" placeholder="Enter your Yo Payments API key">
                        </div>

                        <div class="mb-3">
                            <label for="yo_secret_key" class="form-label">Yo Payments Secret Key</label>
                            <input type="password" class="form-control" id="yo_secret_key" name="yo_secret_key" 
                                   value="{{ old('yo_secret_key') }}" placeholder="Enter your Yo Payments secret key">
                        </div>

                        <div class="mb-3">
                            <label for="yo_merchant_id" class="form-label">Yo Payments Merchant ID</label>
                            <input type="text" class="form-control" id="yo_merchant_id" name="yo_merchant_id" 
                                   value="{{ old('yo_merchant_id') }}" placeholder="Enter your Yo Payments merchant ID">
                        </div>

                        <button type="submit" class="btn btn-primary">Save Payment Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- SMS Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">SMS Gateway Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.sms') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="sms_gateway" class="form-label">SMS Gateway</label>
                            <select class="form-select" id="sms_gateway" name="sms_gateway">
                                <option value="ug_sms" selected>UG SMS</option>
                                <option value="twilio">Twilio</option>
                                <option value="africas_talking">Africa's Talking</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="ug_sms_api_key" class="form-label">UG SMS API Key</label>
                            <input type="text" class="form-control" id="ug_sms_api_key" name="ug_sms_api_key" 
                                   value="{{ old('ug_sms_api_key') }}" placeholder="Enter your UG SMS API key">
                        </div>

                        <div class="mb-3">
                            <label for="ug_sms_username" class="form-label">UG SMS Username</label>
                            <input type="text" class="form-control" id="ug_sms_username" name="ug_sms_username" 
                                   value="{{ old('ug_sms_username') }}" placeholder="Enter your UG SMS username">
                        </div>

                        <div class="mb-3">
                            <label for="ug_sms_sender_id" class="form-label">Sender ID</label>
                            <input type="text" class="form-control" id="ug_sms_sender_id" name="ug_sms_sender_id" 
                                   value="{{ old('ug_sms_sender_id', 'WiFiSaaS') }}" placeholder="Enter sender ID">
                        </div>

                        <button type="submit" class="btn btn-primary">Save SMS Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.system') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="subscription_plan" class="form-label">Subscription Plan</label>
                            <select class="form-select" id="subscription_plan" name="subscription_plan">
                                <option value="basic" {{ $tenant->subscription_plan === 'basic' ? 'selected' : '' }}>Basic</option>
                                <option value="professional" {{ $tenant->subscription_plan === 'professional' ? 'selected' : '' }}>Professional</option>
                                <option value="enterprise" {{ $tenant->subscription_plan === 'enterprise' ? 'selected' : '' }}>Enterprise</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="subscription_expires_at" class="form-label">Subscription Expires</label>
                            <input type="date" class="form-control" id="subscription_expires_at" name="subscription_expires_at" 
                                   value="{{ $tenant->subscription_expires_at ? $tenant->subscription_expires_at->format('Y-m-d') : '' }}">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       value="1" {{ $tenant->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Account Active
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Save System Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Information -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Name:</strong>
                        </div>
                        <div class="col-6">
                            {{ $tenant->name }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Email:</strong>
                        </div>
                        <div class="col-6">
                            {{ $tenant->email }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Business:</strong>
                        </div>
                        <div class="col-6">
                            {{ $tenant->business_name ?? 'Not specified' }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Wallet Balance:</strong>
                        </div>
                        <div class="col-6">
                            UGX {{ number_format($tenant->wallet_balance) }}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Plan:</strong>
                        </div>
                        <div class="col-6">
                            <span class="badge bg-primary">{{ ucfirst($tenant->subscription_plan) }}</span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Status:</strong>
                        </div>
                        <div class="col-6">
                            @if($tenant->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 