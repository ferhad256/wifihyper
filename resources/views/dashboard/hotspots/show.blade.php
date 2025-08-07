@extends('layouts.dashboard')

@section('title', 'Hotspot Details - ' . $hotspot->name)

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Hotspot Details</h1>
            <p class="text-muted">{{ $hotspot->name }}</p>
        </div>
        <div>
            <a href="{{ route('hotspots.edit', $hotspot) }}" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Edit Hotspot
            </a>
            <a href="{{ route('hotspots.packages', $hotspot) }}" class="btn btn-success">
                <i class="fas fa-box me-2"></i>Manage Packages
            </a>
                    <a href="{{ route('dashboard.hotspots') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Hotspots
        </a>
        </div>
    </div>

    <!-- Hotspot Information -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Hotspot Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
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
                                    <td>{{ $hotspot->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Location:</strong></td>
                                    <td>{{ $hotspot->location ?? 'Not specified' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td>{{ $hotspot->description ?? 'No description' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Packages:</strong></td>
                                    <td>{{ $hotspot->packages->count() }} configured</td>
                                </tr>
                                <tr>
                                    <td><strong>Last Updated:</strong></td>
                                    <td>{{ $hotspot->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Packages Overview -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">WiFi Packages</h6>
                    <a href="{{ route('hotspots.packages', $hotspot) }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Package
                    </a>
                </div>
                <div class="card-body">
                    @if($hotspot->packages->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Package Name</th>
                                        <th>Price</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($hotspot->packages as $package)
                                    <tr>
                                        <td>
                                            <strong>{{ $package->name }}</strong>
                                            @if($package->description)
                                                <br><small class="text-muted">{{ $package->description }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <strong class="text-primary">UGX {{ number_format($package->price) }}</strong>
                                        </td>
                                        <td>
                                            @if($package->duration_hours)
                                                <i class="fas fa-clock me-1"></i>{{ $package->duration_hours }} hours
                                            @else
                                                <span class="text-muted">Unlimited</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($package->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-gray-300 mb-3"></i>
                            <h5 class="text-gray-500">No Packages Configured</h5>
                            <p class="text-gray-500">This hotspot doesn't have any packages yet.</p>
                            <a href="{{ route('hotspots.packages', $hotspot) }}" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Your First Package
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('portal.index', $hotspot->name) }}" class="btn btn-info" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>View Portal
                        </a>
                        <a href="{{ route('portal.test', $hotspot) }}" class="btn btn-warning" target="_blank">
                            <i class="fas fa-vial me-2"></i>Test Portal
                        </a>
                        <a href="{{ route('hotspots.packages', $hotspot) }}" class="btn btn-success">
                            <i class="fas fa-box me-2"></i>Manage Packages
                        </a>
                        <a href="{{ route('hotspots.edit', $hotspot) }}" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Hotspot
                        </a>
                    </div>
                </div>
            </div>

            <!-- Portal Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Portal Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Portal URL:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="{{ route('portal.index', $hotspot->name) }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard(this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Test URL:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="{{ route('portal.test', $hotspot) }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard(this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Use these URLs to test your captive portal or share with customers.
                    </small>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-primary">{{ $hotspot->packages->count() }}</h4>
                                <small class="text-muted">Packages</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success">{{ $hotspot->packages->where('is_active', true)->count() }}</h4>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
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