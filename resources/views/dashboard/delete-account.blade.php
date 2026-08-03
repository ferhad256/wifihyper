@extends('layouts.dashboard')

@section('title', 'Delete Account')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-end mb-4">
        <a href="{{ route('dashboard.profile') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Profile
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Warning Alert -->
            <div class="alert alert-danger border-danger">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-1">⚠️ Irreversible Action</h5>
                        <p class="mb-0">You are about to permanently delete your account. This action cannot be undone.</p>
                    </div>
                </div>
            </div>

            <!-- Account Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-warning text-dark">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle me-2"></i>What Will Be Deleted
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2 mb-3">
                            <div class="border rounded p-3">
                                <i class="fas fa-wifi fa-2x text-primary mb-2"></i>
                                <div class="h4 text-primary">{{ $stats['hotspots'] }}</div>
                                <small class="text-muted">Hotspots</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="border rounded p-3">
                                <i class="fas fa-ticket-alt fa-2x text-success mb-2"></i>
                                <div class="h4 text-success">{{ $stats['vouchers'] }}</div>
                                <small class="text-muted">Vouchers</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="border rounded p-3">
                                <i class="fas fa-exchange-alt fa-2x text-info mb-2"></i>
                                <div class="h4 text-info">{{ $stats['transactions'] }}</div>
                                <small class="text-muted">Transactions</small>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="border rounded p-3">
                                <i class="fas fa-box fa-2x text-warning mb-2"></i>
                                <div class="h4 text-warning">{{ $stats['packages'] }}</div>
                                <small class="text-muted">Packages</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 {{ $stats['wallet_balance'] > 0 ? 'border-danger bg-danger-light' : '' }}">
                                <i class="fas fa-piggy-bank fa-2x {{ $stats['wallet_balance'] > 0 ? 'text-danger' : 'text-secondary' }} mb-2"></i>
                                <div class="h4 {{ $stats['wallet_balance'] > 0 ? 'text-danger' : 'text-secondary' }}">
                                    UGX {{ number_format($stats['wallet_balance']) }}
                                </div>
                                <small class="text-muted">Wallet Balance</small>
                                @if($stats['wallet_balance'] > 0)
                                    <div class="mt-2">
                                        <small class="text-danger fw-bold">⚠️ Must be withdrawn first</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>Additional Data That Will Be Lost:
                        </h6>
                        <ul class="mb-0">
                            <li>All business information and settings</li>
                            <li>Wallet balance and transaction history</li>
                            <li>Subscription plans and billing information</li>
                            <li>Customer data and usage statistics</li>
                            <li>Email notifications and system logs</li>
                            <li>Custom configurations and preferences</li>
                        </ul>
                    </div>
                </div>
            </div>

            @if($stats['wallet_balance'] > 0)
            <!-- Wallet Balance Warning -->
            <div class="card shadow border-danger mb-4">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>Account Deletion Blocked
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger mb-3">
                        <h6 class="alert-heading">
                            <i class="fas fa-ban me-2"></i>Cannot Delete Account
                        </h6>
                        <p class="mb-2">
                            You have a wallet balance of <strong>UGX {{ number_format($stats['wallet_balance']) }}</strong>. 
                            You must withdraw all funds before deleting your account.
                        </p>
                    </div>
                    
                    <div class="text-center">
                        <h6 class="text-muted mb-3">What you need to do:</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                                    <h6>1. Withdraw Funds</h6>
                                    <p class="text-muted small">Go to the dashboard page and withdraw all remaining funds from your wallet.</p>
                                    <a href="{{ route('dashboard') }}" class="btn btn-success btn-sm">
                                        <i class="fas fa-tachometer-alt me-1"></i>Go to Dashboard
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <i class="fas fa-trash-alt fa-2x text-danger mb-2"></i>
                                    <h6>2. Delete Account</h6>
                                    <p class="text-muted small">Once your wallet balance is zero, you can return here to delete your account.</p>
                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                        <i class="fas fa-lock me-1"></i>Currently Blocked
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Confirmation Form -->
            <div class="card shadow border-danger {{ $stats['wallet_balance'] > 0 ? 'opacity-50' : '' }}">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-trash-alt me-2"></i>Final Confirmation
                        @if($stats['wallet_balance'] > 0)
                            <span class="badge bg-warning ms-2">Blocked</span>
                        @endif
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.delete.confirm') }}" id="deleteAccountForm">
                        @csrf
                        @method('DELETE')
                        
                        <div class="alert alert-danger">
                            <h6 class="alert-heading mb-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>Final Warning
                            </h6>
                            <p class="mb-2">To confirm account deletion, you must:</p>
                            <ol class="mb-0">
                                <li>Type <strong>"DELETE MY ACCOUNT"</strong> exactly as shown</li>
                                <li>Enter your current password</li>
                                <li>Understand that this action is permanent and irreversible</li>
                            </ol>
                        </div>

                        @if($stats['wallet_balance'] > 0)
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Account deletion is currently blocked.</strong> 
                                You must withdraw your wallet balance of UGX {{ number_format($stats['wallet_balance']) }} before you can delete your account.
                            </div>
                        @endif

                        @error('wallet_balance')
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>{{ $message }}
                            </div>
                        @enderror

                        <div class="mb-3">
                            <label for="confirmation_text" class="form-label">
                                Type "DELETE MY ACCOUNT" to confirm
                            </label>
                            <input type="text" class="form-control @error('confirmation_text') is-invalid @enderror" 
                                   id="confirmation_text" name="confirmation_text" 
                                   placeholder="DELETE MY ACCOUNT" 
                                   {{ $stats['wallet_balance'] > 0 ? 'disabled' : '' }} required>
                            @error('confirmation_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">
                                Enter your current password
                            </label>
                            <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" 
                                   id="password_confirmation" name="password_confirmation" 
                                   {{ $stats['wallet_balance'] > 0 ? 'disabled' : '' }} required>
                            @error('password_confirmation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="understand_consequences" 
                                   {{ $stats['wallet_balance'] > 0 ? 'disabled' : '' }} required>
                            <label class="form-check-label" for="understand_consequences">
                                I understand that deleting my account will permanently remove all my data and this action cannot be undone.
                            </label>
                        </div>

                        <div class="d-grid gap-2">
                            @if($stats['wallet_balance'] > 0)
                                <button type="button" class="btn btn-secondary btn-lg" disabled>
                                    <i class="fas fa-lock me-2"></i>Account Deletion Blocked - Withdraw Funds First
                                </button>
                            @else
                                <button type="submit" class="btn btn-danger btn-lg" id="deleteButton" disabled>
                                    <i class="fas fa-trash-alt me-2"></i>Permanently Delete My Account
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Alternative Actions -->
            <div class="card shadow mt-4">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-lightbulb me-2"></i>Alternatives to Account Deletion
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="text-center p-3">
                                <i class="fas fa-pause fa-2x text-warning mb-2"></i>
                                <h6>Deactivate Account</h6>
                                <p class="text-muted small">Temporarily disable your account instead of deleting it permanently.</p>
                                <a href="{{ route('dashboard.profile') }}" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-pause me-1"></i>Deactivate Instead
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-center p-3">
                                <i class="fas fa-download fa-2x text-info mb-2"></i>
                                <h6>Export Data</h6>
                                <p class="text-muted small">Download your data before deletion for backup purposes.</p>
                                <button class="btn btn-outline-info btn-sm" disabled>
                                    <i class="fas fa-download me-1"></i>Export Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmationText = document.getElementById('confirmation_text');
    const passwordConfirmation = document.getElementById('password_confirmation');
    const understandCheckbox = document.getElementById('understand_consequences');
    const deleteButton = document.getElementById('deleteButton');
    const deleteForm = document.getElementById('deleteAccountForm');

    function validateForm() {
        const isConfirmationCorrect = confirmationText.value === 'DELETE MY ACCOUNT';
        const hasPassword = passwordConfirmation.value.length > 0;
        const understandsConsequences = understandCheckbox.checked;

        deleteButton.disabled = !(isConfirmationCorrect && hasPassword && understandsConsequences);
    }

    confirmationText.addEventListener('input', validateForm);
    passwordConfirmation.addEventListener('input', validateForm);
    understandCheckbox.addEventListener('change', validateForm);

    // Form submission confirmation
    deleteForm.addEventListener('submit', function(e) {
        if (!confirm('Are you absolutely sure you want to delete your account? This action cannot be undone and all your data will be permanently lost.')) {
            e.preventDefault();
            return false;
        }
    });

    // Real-time validation feedback
    confirmationText.addEventListener('input', function() {
        if (this.value === 'DELETE MY ACCOUNT') {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            if (this.value.length > 0) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        }
    });
});
</script>
@endpush 