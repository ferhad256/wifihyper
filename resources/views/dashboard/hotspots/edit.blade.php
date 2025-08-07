@extends('layouts.dashboard')

@section('title', 'Edit Hotspot - ' . $hotspot->name)

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Edit Hotspot</h1>
            <p class="text-muted">{{ $hotspot->name }}</p>
        </div>
        <div>
            <a href="{{ route('hotspots.show', $hotspot) }}" class="btn btn-info">
                <i class="fas fa-eye me-2"></i>View Details
            </a>
                    <a href="{{ route('dashboard.hotspots') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Hotspots
        </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Hotspot Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('hotspots.update', $hotspot) }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Hotspot Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $hotspot->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ssid" class="form-label">WiFi SSID</label>
                                    <input type="text" class="form-control @error('ssid') is-invalid @enderror" 
                                           id="ssid" name="ssid" value="{{ old('ssid', $hotspot->ssid) }}" required>
                                    @error('ssid')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                           id="location" name="location" value="{{ old('location', $hotspot->location) }}" 
                                           placeholder="e.g., Kampala, Uganda">
                                    @error('location')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               value="1" {{ old('is_active', $hotspot->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Hotspot is active
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="4" 
                                      placeholder="Describe your hotspot location, features, or any additional information...">{{ old('description', $hotspot->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Hotspot
                            </button>
                            <a href="{{ route('hotspots.show', $hotspot) }}" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Current Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Current Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>{{ $hotspot->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>SSID:</strong></td>
                            <td>{{ $hotspot->ssid }}</td>
                        </tr>
                        <tr>
                            <td><strong>Location:</strong></td>
                            <td>{{ $hotspot->location ?? 'Not specified' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                @if($hotspot->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td>{{ $hotspot->created_at->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Packages:</strong></td>
                            <td>{{ $hotspot->packages->count() }} configured</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('hotspots.show', $hotspot) }}" class="btn btn-info">
                            <i class="fas fa-eye me-2"></i>View Details
                        </a>
                        <a href="{{ route('hotspots.packages', $hotspot) }}" class="btn btn-success">
                            <i class="fas fa-box me-2"></i>Manage Packages
                        </a>
                        <a href="{{ route('portal.index', $hotspot->url_name) }}" class="btn btn-warning" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>View Portal
                        </a>
                    </div>
                </div>
            </div>

            <!-- Portal URLs -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Portal URLs</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Portal URL:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" value="{{ route('portal.index', $hotspot->name) }}" readonly>
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyToClipboard(this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Test URL:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" value="{{ route('portal.test', $hotspot) }}" readonly>
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyToClipboard(this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        These URLs will remain the same after updating.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyToClipboard(button) {
    const input = button.parentElement.querySelector('input');
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    // Show feedback
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i>';
    button.classList.remove('btn-outline-secondary');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
</script>
@endpush
@endsection 