@extends('layouts.dashboard')

@section('title', 'Vouchers Management')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Vouchers Management</h1>
        <div>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#uploadMultipleModal">
                <i class="fas fa-upload me-2"></i>Upload Multiple
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVoucherModal">
                <i class="fas fa-plus me-2"></i>Add Voucher
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Available Vouchers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $totalVouchers }}
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
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Unused Vouchers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $unusedVouchers }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                Used Vouchers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $usedVouchers }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Expired Vouchers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $expiredVouchers }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vouchers Grouped by Package -->
    @if($vouchersByPackage->count() > 0)
        @foreach($vouchersByPackage as $packageId => $vouchers)
            @php
                $package = $vouchers->first()->package;
                $packageName = $package ? $package->name : 'No Package';
                $unusedCount = $vouchers->where('status', 'unused')->count();
            @endphp
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">{{ $packageName }}</h6>
                        <small class="text-muted">
                            {{ $vouchers->count() }} available vouchers
                        </small>
                    </div>
                    <div>
                        <button class="btn btn-success btn-sm me-2" 
                                onclick="uploadForPackage({{ $packageId }}, '{{ $packageName }}')">
                            <i class="fas fa-upload me-1"></i>Add More
                        </button>
                        <a href="{{ route('vouchers.export') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-download me-1"></i>Export
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Voucher Code</th>
                                    <th>Package</th>
                                    <th>Status</th>
                                    <th>Expires At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vouchers->take(10) as $voucher)
                                <tr>
                                    <td>
                                        <code class="text-primary">{{ $voucher->code }}</code>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $voucher->package->name ?? 'No Package' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Available</span>
                                    </td>
                                    <td>{{ $voucher->expires_at ? $voucher->expires_at->format('M d, Y') : 'No Expiry' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('vouchers.destroy', $voucher) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this voucher?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    @if($vouchers->count() > 10)
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Showing first 10 vouchers. Total: {{ $vouchers->count() }} available vouchers for this package.
                            </small>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @else
        <div class="card shadow mb-4">
            <div class="card-body text-center py-4">
                <i class="fas fa-ticket-alt fa-3x text-gray-300 mb-3"></i>
                <p class="text-gray-500">No available vouchers found. Start by adding vouchers manually or uploading multiple vouchers.</p>
                <p class="text-muted small">Note: Used vouchers are only shown on the Billing & Transactions page.</p>
            </div>
        </div>
    @endif
</div>

<!-- Upload Multiple Vouchers Modal -->
<div class="modal fade" id="uploadMultipleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Multiple Vouchers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('vouchers.upload-multiple') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="package_id" class="form-label">Package</label>
                        <select class="form-select @error('package_id') is-invalid @enderror" 
                                id="package_id" name="package_id" required>
                            <option value="">Select a package</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}" {{ old('package_id') == $package->id ? 'selected' : '' }}>
                                    {{ $package->name }} - {{ $package->hotspot->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('package_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="voucher_codes" class="form-label">Voucher Codes</label>
                        <textarea class="form-control @error('voucher_codes') is-invalid @enderror" 
                                  id="voucher_codes" name="voucher_codes" rows="8" 
                                  placeholder="Enter voucher codes (one per line or comma-separated)&#10;Example:&#10;VOUCHER001&#10;VOUCHER002&#10;VOUCHER003" required>{{ old('voucher_codes') }}</textarea>
                        <div class="form-text">
                            Enter voucher codes separated by commas or new lines. Duplicate codes will be skipped.
                        </div>
                        @error('voucher_codes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="expires_at" class="form-label">Expiry Date (Optional)</label>
                        <input type="date" class="form-control @error('expires_at') is-invalid @enderror" 
                               id="expires_at" name="expires_at" value="{{ old('expires_at') }}">
                        @error('expires_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload me-1"></i>Upload Vouchers
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Voucher Modal -->
<div class="modal fade" id="addVoucherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('vouchers.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="code" class="form-label">Voucher Code</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" 
                               id="code" name="code" value="{{ old('code') }}" required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="package_id_single" class="form-label">Package</label>
                        <select class="form-select @error('package_id') is-invalid @enderror" 
                                id="package_id_single" name="package_id">
                            <option value="">No Package (General)</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}" {{ old('package_id') == $package->id ? 'selected' : '' }}>
                                    {{ $package->name }} - {{ $package->hotspot->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('package_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="expires_at_single" class="form-label">Expiry Date (Optional)</label>
                        <input type="date" class="form-control @error('expires_at') is-invalid @enderror" 
                               id="expires_at_single" name="expires_at" value="{{ old('expires_at') }}">
                        @error('expires_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function uploadForPackage(packageId, packageName) {
    // Set the package in the upload modal
    document.getElementById('package_id').value = packageId;
    
    // Update modal title
    document.querySelector('#uploadMultipleModal .modal-title').textContent = `Upload Vouchers for ${packageName}`;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('uploadMultipleModal'));
    modal.show();
}
</script>
@endpush
@endsection 