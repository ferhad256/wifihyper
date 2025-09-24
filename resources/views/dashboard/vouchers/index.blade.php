@extends('layouts.dashboard')

@section('title', 'Vouchers Management')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Vouchers Management</h1>
        <div>
            <a href="{{ route('vouchers.manual-sms') }}" class="btn btn-info me-2">
                <i class="fas fa-sms me-2"></i>Send SMS Voucher
            </a>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#uploadMultipleModal">
                <i class="fas fa-upload me-2"></i>Upload Multiple
            </button>
            <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                <i class="fas fa-file-csv me-2"></i>Upload CSV
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

    <!-- Vouchers Grouped by Hotspot -->
    @if($vouchersByHotspot->count() > 0)
        @foreach($vouchersByHotspot as $hotspotId => $vouchers)
            @php
                $hotspot = $vouchers->first()->package ? $vouchers->first()->package->hotspot : null;
                $hotspotName = $hotspot ? $hotspot->name : 'No Hotspot';
                $unusedCount = $vouchers->where('status', 'unused')->count();
            @endphp
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-wifi me-2"></i>{{ $hotspotName }}
                        </h6>
                        <small class="text-muted">
                            {{ $vouchers->count() }} available vouchers across {{ $vouchers->groupBy('package_id')->count() }} packages
                        </small>
                    </div>
                    <div>
                        <button class="btn btn-success btn-sm me-2" 
                                onclick="uploadForHotspot({{ $hotspotId }}, '{{ $hotspotName }}')">
                            <i class="fas fa-upload me-1"></i>Add More
                        </button>
                        <button class="btn btn-warning btn-sm me-2" 
                                onclick="uploadCsvForHotspot({{ $hotspotId }}, '{{ $hotspotName }}')">
                            <i class="fas fa-file-csv me-1"></i>Upload CSV
                        </button>
                        <button class="btn btn-danger btn-sm me-2" 
                                onclick="deleteAllVouchersForHotspot({{ $hotspotId }}, '{{ $hotspotName }}', {{ $vouchers->count() }})">
                            <i class="fas fa-trash me-1"></i>Delete All
                        </button>
                        <a href="{{ route('vouchers.export') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-download me-1"></i>Export
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @php
                        $vouchersByPackage = $vouchers->groupBy('package_id');
                    @endphp
                    
                    @foreach($vouchersByPackage as $packageId => $packageVouchers)
                        @php
                            $package = $packageVouchers->first()->package;
                            $packageName = $package ? $package->name : 'No Package';
                        @endphp
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-secondary mb-0">
                                    <i class="fas fa-box me-2"></i>{{ $packageName }}
                                    <span class="badge bg-secondary ms-2">{{ $packageVouchers->count() }} vouchers</span>
                                </h6>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deletePackageVouchers({{ $packageId }}, '{{ $packageName }}', {{ $packageVouchers->count() }})">
                                    <i class="fas fa-trash me-1"></i>Delete Package Vouchers
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Voucher Code</th>
                                            <th>Status</th>
                                            <th>Expires At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($packageVouchers->take(5) as $voucher)
                                        <tr>
                                            <td>
                                                <code class="text-primary">{{ $voucher->code }}</code>
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
                            
                            @if($packageVouchers->count() > 5)
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Showing first 5 vouchers. Total: {{ $packageVouchers->count() }} available vouchers for this package.
                                    </small>
                                </div>
                            @endif
                        </div>
                    @endforeach
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
                                  placeholder="Enter voucher codes (one per line or comma-separated):&#10;VOUCHER001&#10;VOUCHER002,VOUCHER003&#10;VOUCHER004" required>{{ old('voucher_codes') }}</textarea>
                        <div class="form-text">
                            <ul class="mb-0">
                                <li><strong>One voucher per line</strong> (recommended)</li>
                                <li><strong>Comma-separated</strong> on same line: VOUCHER001,VOUCHER002</li>
                                <li>Codes must be 3-20 characters (letters and numbers only)</li>
                                <li>Duplicate codes will be skipped automatically</li>
                            </ul>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="loadSampleVouchers()">
                                <i class="fas fa-file-alt"></i> Load Sample
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="testVoucherParsing()">
                                <i class="fas fa-eye"></i> Test Parsing
                            </button>
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

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Vouchers from CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('vouchers.upload-csv') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="csv_package_id" class="form-label">Package</label>
                        <select class="form-select @error('package_id') is-invalid @enderror" 
                                id="csv_package_id" name="package_id" required>
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
                        <label for="csv_file" class="form-label">CSV File</label>
                        <input type="file" class="form-control @error('csv_file') is-invalid @enderror" 
                               id="csv_file" name="csv_file" accept=".csv,.txt" required>
                        <div class="form-text">
                            Upload a CSV file with voucher codes. The file should contain one voucher code per line or use standard CSV format.
                            <br><strong>Supported formats:</strong>
                            <ul class="mb-0 mt-1">
                                <li>Plain text file with one code per line</li>
                                <li>CSV file with headers (first column will be used)</li>
                                <li>CSV file without headers (first column will be used)</li>
                            </ul>
                        </div>
                        @error('csv_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="csv_expires_at" class="form-label">Expiry Date (Optional)</label>
                        <input type="date" class="form-control @error('expires_at') is-invalid @enderror" 
                               id="csv_expires_at" name="expires_at" value="{{ old('expires_at') }}">
                        @error('expires_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>CSV Format Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Maximum file size: 2MB</li>
                            <li>Duplicate codes will be automatically skipped</li>
                            <li>Empty lines will be ignored</li>
                            <li>Only unused vouchers will be created</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-file-csv me-1"></i>Upload CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete All Vouchers Confirmation Modal -->
<div class="modal fade" id="deleteAllModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete All Vouchers
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('vouchers.delete-all-package') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <input type="hidden" id="delete_package_id" name="package_id">
                    
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h5>Are you sure you want to delete all vouchers?</h5>
                        <p class="text-muted">This action cannot be undone.</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Package:</strong> <span id="delete_package_name"></span><br>
                        <strong>Vouchers to delete:</strong> <span id="delete_voucher_count"></span> unused vouchers
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_delete" class="form-label">Type "DELETE" to confirm</label>
                        <input type="text" class="form-control" id="confirm_delete" 
                               placeholder="Type DELETE to confirm" required>
                        <div class="form-text">This helps prevent accidental deletions.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirm_delete_btn" disabled>
                        <i class="fas fa-trash me-1"></i>Delete All Vouchers
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Package Vouchers Confirmation Modal -->
<div class="modal fade" id="deletePackageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Package Vouchers
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('vouchers.delete-all-package') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <input type="hidden" id="delete_package_id_modal" name="package_id">
                    
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h5>Are you sure you want to delete all vouchers for this package?</h5>
                        <p class="text-muted">This action cannot be undone.</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Package:</strong> <span id="delete_package_name_modal"></span><br>
                        <strong>Vouchers to delete:</strong> <span id="delete_package_voucher_count"></span> unused vouchers
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_package_delete" class="form-label">Type "DELETE" to confirm</label>
                        <input type="text" class="form-control" id="confirm_package_delete" 
                               placeholder="Type DELETE to confirm" required>
                        <div class="form-text">This helps prevent accidental deletions.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirm_package_delete_btn" disabled>
                        <i class="fas fa-trash me-1"></i>Delete Package Vouchers
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function uploadForHotspot(hotspotId, hotspotName) {
    // Show the upload modal for hotspot
    document.querySelector('#uploadMultipleModal .modal-title').textContent = `Upload Vouchers for ${hotspotName}`;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('uploadMultipleModal'));
    modal.show();
}

function uploadCsvForHotspot(hotspotId, hotspotName) {
    // Show the CSV upload modal for hotspot
    document.querySelector('#uploadCsvModal .modal-title').textContent = `Upload CSV for ${hotspotName}`;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('uploadCsvModal'));
    modal.show();
}

function deleteAllVouchersForHotspot(hotspotId, hotspotName, voucherCount) {
    // Show the delete modal for hotspot
    document.getElementById('delete_package_name').textContent = hotspotName;
    document.getElementById('delete_voucher_count').textContent = voucherCount;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('deleteAllModal'));
    modal.show();
}

function deletePackageVouchers(packageId, packageName, voucherCount) {
    // Show the delete modal for package
    document.getElementById('delete_package_id_modal').value = packageId;
    document.getElementById('delete_package_name_modal').textContent = packageName;
    document.getElementById('delete_package_voucher_count').textContent = voucherCount;
    
    // Reset confirmation input
    document.getElementById('confirm_package_delete').value = '';
    document.getElementById('confirm_package_delete_btn').disabled = true;
    document.getElementById('confirm_package_delete_btn').classList.remove('btn-danger');
    document.getElementById('confirm_package_delete_btn').classList.add('btn-secondary');
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('deletePackageModal'));
    modal.show();
}

// Handle package delete confirmation input
document.getElementById('confirm_package_delete').addEventListener('input', function() {
    const confirmBtn = document.getElementById('confirm_package_delete_btn');
    const input = this.value.trim();
    
    if (input === 'DELETE') {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('btn-secondary');
        confirmBtn.classList.add('btn-danger');
    } else {
        confirmBtn.disabled = true;
        confirmBtn.classList.remove('btn-danger');
        confirmBtn.classList.add('btn-secondary');
    }
});

// Reset package delete modal when closed
document.getElementById('deletePackageModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('confirm_package_delete').value = '';
    document.getElementById('confirm_package_delete_btn').disabled = true;
    document.getElementById('confirm_package_delete_btn').classList.remove('btn-danger');
    document.getElementById('confirm_package_delete_btn').classList.add('btn-secondary');
});

// Handle delete confirmation input
document.getElementById('confirm_delete').addEventListener('input', function() {
    const confirmBtn = document.getElementById('confirm_delete_btn');
    const input = this.value.trim();
    
    if (input === 'DELETE') {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('btn-secondary');
        confirmBtn.classList.add('btn-danger');
    } else {
        confirmBtn.disabled = true;
        confirmBtn.classList.remove('btn-danger');
        confirmBtn.classList.add('btn-secondary');
    }
});

// Reset delete modal when closed
document.getElementById('deleteAllModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('confirm_delete').value = '';
    document.getElementById('confirm_delete_btn').disabled = true;
    document.getElementById('confirm_delete_btn').classList.remove('btn-danger');
    document.getElementById('confirm_delete_btn').classList.add('btn-secondary');
});

function testVoucherParsing() {
    const voucherCodesTextarea = document.getElementById('voucher_codes');
    const voucherCodes = voucherCodesTextarea.value.trim();
    const codes = [];
    const invalidCodes = [];

    if (voucherCodes === '') {
        alert('Please enter voucher codes in the textarea.');
        return;
    }

    // Use the same parsing logic as the controller
    const rawInput = voucherCodes.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    const lines = rawInput.split('\n');
    
    for (let line of lines) {
        line = line.trim();
        if (line === '') continue;
        
        // If line contains commas, split by commas too
        if (line.includes(',')) {
            const commaParts = line.split(',');
            for (let part of commaParts) {
                part = part.trim();
                if (part !== '') {
                    if (part.length >= 3 && part.length <= 20 && /^[a-zA-Z0-9]+$/.test(part)) {
                        codes.push(part);
                    } else {
                        invalidCodes.push(part);
                    }
                }
            }
        } else {
            if (line.length >= 3 && line.length <= 20 && /^[a-zA-Z0-9]+$/.test(line)) {
                codes.push(line);
            } else {
                invalidCodes.push(line);
            }
        }
    }
    
    // Remove duplicates
    const uniqueCodes = [...new Set(codes)];

    if (uniqueCodes.length === 0) {
        alert('No valid voucher codes found after parsing.');
        return;
    }

    alert(`Parsed ${uniqueCodes.length} unique voucher codes.\nInvalid codes: ${invalidCodes.length}`);
    console.log('Valid Codes:', uniqueCodes);
    console.log('Invalid Codes:', invalidCodes);
}

function loadSampleVouchers() {
    const voucherCodesTextarea = document.getElementById('voucher_codes');
    voucherCodesTextarea.value = `VOUCHER001
VOUCHER002,VOUCHER003
VOUCHER004
VOUCHER005,VOUCHER006,VOUCHER007`;
    alert('Sample vouchers loaded. You can now test parsing or modify them.');
}
</script>
@endpush
@endsection 