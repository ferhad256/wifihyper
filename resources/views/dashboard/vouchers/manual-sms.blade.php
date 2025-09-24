@extends('layouts.dashboard')

@section('title', 'Send Voucher via SMS')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-sms me-2"></i>Send Voucher via SMS
        </h1>
        <div>
            <a href="{{ route('vouchers.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-2"></i>Back to Vouchers
            </a>
        </div>
    </div>

    <!-- Manual SMS Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-mobile-alt me-2"></i>Send Voucher Manually
                    </h5>
                </div>
                <div class="card-body">
                    @if($packages->isEmpty())
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>No Available Vouchers!</strong> You need to have unused vouchers for at least one package to send SMS vouchers.
                            <a href="{{ route('vouchers.index') }}" class="alert-link">Go to Vouchers</a> to upload some vouchers first.
                        </div>
                    @else
                        <form action="{{ route('vouchers.send-sms') }}" method="POST" id="manualSmsForm">
                            @csrf
                            
                            <!-- Phone Number -->
                            <div class="mb-4">
                                <label for="phone_number" class="form-label">
                                    <i class="fas fa-phone me-2"></i>Client Phone Number
                                </label>
                                <input type="text" 
                                       class="form-control @error('phone_number') is-invalid @enderror" 
                                       id="phone_number" 
                                       name="phone_number" 
                                       value="{{ old('phone_number') }}"
                                       placeholder="e.g., 07xxxxxxxxx or 2567xxxxxxxx"
                                       required>
                                @error('phone_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Package Selection -->
                            <div class="mb-4">
                                <label for="package_id" class="form-label">
                                    <i class="fas fa-box me-2"></i>Select Package
                                </label>
                                <select class="form-select @error('package_id') is-invalid @enderror" 
                                        id="package_id" 
                                        name="package_id" 
                                        required>
                                    <option value="">Choose a package...</option>
                                    @foreach($packages as $package)
                                        <option value="{{ $package->id }}" 
                                                {{ old('package_id') == $package->id ? 'selected' : '' }}
                                                data-hotspot="{{ $package->hotspot->name }}"
                                                data-price="{{ $package->formatted_price }}"
                                                data-duration="{{ $package->formatted_duration }}"
                                                data-data-limit="{{ $package->formatted_data_limit }}"
                                                data-available="{{ $package->available_vouchers }}">
                                            {{ $package->name }} 
                                            ({{ $package->hotspot->name }}) 
                                            - {{ $package->available_vouchers }} available
                                        </option>
                                    @endforeach
                                </select>
                                @error('package_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Package Details Preview -->
                            <div id="packageDetails" class="mb-4" style="display: none;">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-info-circle me-2"></i>Package Details
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted">Hotspot:</small>
                                                <div id="hotspotName" class="fw-bold"></div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Price:</small>
                                                <div id="packagePrice" class="fw-bold text-success"></div>
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <small class="text-muted">Duration:</small>
                                                <div id="packageDuration" class="fw-bold"></div>
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <small class="text-muted">Data Limit:</small>
                                                <div id="packageDataLimit" class="fw-bold"></div>
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <small class="text-muted">Available Vouchers:</small>
                                                <div id="availableVouchers" class="fw-bold text-primary"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary me-md-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                                <button type="submit" class="btn btn-primary" id="sendButton">
                                    <i class="fas fa-paper-plane me-2"></i>Send Voucher via SMS
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Instructions Panel -->
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Instructions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">
                            <i class="fas fa-mobile-alt me-2"></i>How it works:
                        </h6>
                        <ol class="small">
                            <li>Enter the client's phone number</li>
                            <li>Select a package with available vouchers</li>
                            <li>Click "Send Voucher via SMS"</li>
                            <li>The system will automatically select an unused voucher</li>
                            <li>The voucher code will be sent via SMS</li>
                            <li>The voucher will be marked as used</li>
                        </ol>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-success">
                            <i class="fas fa-check-circle me-2"></i>Phone Number Formats:
                        </h6>
                        <ul class="small">
                            <li><code>07xxxxxxxxx</code> (10 digits)</li>
                            <li><code>2567xxxxxxxx</code> (12 digits)</li>
                            <li><code>+2567xxxxxxxx</code> (with +)</li>
                        </ul>
                    </div>

                    <div class="alert alert-light border">
                        <small class="text-muted">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tip:</strong> This feature is perfect for walk-in customers or when you need to send vouchers manually to clients.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const packageSelect = document.getElementById('package_id');
    const packageDetails = document.getElementById('packageDetails');
    const sendButton = document.getElementById('sendButton');
    const form = document.getElementById('manualSmsForm');

    // Show package details when package is selected
    packageSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            // Show package details
            document.getElementById('hotspotName').textContent = selectedOption.dataset.hotspot;
            document.getElementById('packagePrice').textContent = selectedOption.dataset.price;
            document.getElementById('packageDuration').textContent = selectedOption.dataset.duration;
            document.getElementById('packageDataLimit').textContent = selectedOption.dataset.dataLimit;
            document.getElementById('availableVouchers').textContent = selectedOption.dataset.available + ' vouchers';
            
            packageDetails.style.display = 'block';
            
            // Enable send button
            sendButton.disabled = false;
        } else {
            packageDetails.style.display = 'none';
            sendButton.disabled = true;
        }
    });

    // Form submission with confirmation
    form.addEventListener('submit', function(e) {
        const phoneNumber = document.getElementById('phone_number').value;
        const packageName = packageSelect.options[packageSelect.selectedIndex].text;
        
        if (!confirm(`Are you sure you want to send a voucher for "${packageName}" to ${phoneNumber}?\n\nThis action cannot be undone.`)) {
            e.preventDefault();
        }
    });

    // Phone number formatting
    const phoneInput = document.getElementById('phone_number');
    phoneInput.addEventListener('input', function() {
        // Remove any non-numeric characters except +
        this.value = this.value.replace(/[^0-9+]/g, '');
    });
});

function resetForm() {
    document.getElementById('manualSmsForm').reset();
    document.getElementById('packageDetails').style.display = 'none';
    document.getElementById('sendButton').disabled = true;
}
</script>
@endsection
