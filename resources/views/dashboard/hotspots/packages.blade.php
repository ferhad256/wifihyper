@extends('layouts.dashboard')

@section('title', 'Manage Packages - ' . $hotspot->name)

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Manage Packages</h1>
            <p class="text-muted">{{ $hotspot->name }} - {{ $hotspot->ssid }}</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                <i class="fas fa-plus me-2"></i>Add Package
            </button>
                    <a href="{{ route('dashboard.hotspots') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Hotspots
        </a>
        </div>
    </div>

    <!-- Hotspot Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Hotspot Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Name:</strong> {{ $hotspot->name }}<br>
                            <strong>SSID:</strong> {{ $hotspot->ssid }}<br>
                            <strong>Status:</strong> 
                            @if($hotspot->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <strong>Location:</strong> {{ $hotspot->location ?? 'Not specified' }}<br>
                            <strong>Description:</strong> {{ $hotspot->description ?? 'No description' }}<br>
                            <strong>Packages:</strong> {{ $hotspot->packages->count() }} configured
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Packages List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">WiFi Packages</h6>
                    <span class="badge bg-primary">{{ $packages->count() }} packages</span>
                </div>
                <div class="card-body">
                    @if($packages->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Package Name</th>
                                        <th>Description</th>
                                        <th>Duration</th>
                                        <th>Data Limit</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($packages as $package)
                                    <tr>
                                        <td>
                                            <strong>{{ $package->name }}</strong>
                                        </td>
                                        <td>{{ $package->description ?? 'No description' }}</td>
                                        <td>
                                            @if($package->duration_hours)
                                                <i class="fas fa-clock me-1"></i>{{ $package->duration_hours }} hours
                                            @else
                                                <span class="text-muted">Unlimited</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($package->data_limit_mb)
                                                <i class="fas fa-database me-1"></i>{{ $package->data_limit_mb }}MB
                                            @else
                                                <span class="text-muted">Unlimited</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong class="text-primary">UGX {{ number_format($package->price) }}</strong>
                                        </td>
                                        <td>
                                            @if($package->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editPackage({{ $package->id }})">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deletePackage({{ $package->id }})">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-gray-300 mb-3"></i>
                            <h5 class="text-gray-500">No Packages Found</h5>
                            <p class="text-gray-500">Get started by creating your first WiFi package.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                                <i class="fas fa-plus me-2"></i>Create Your First Package
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Package Modal -->
<div class="modal fade" id="addPackageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('hotspots.packages.store', $hotspot) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Package Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  id="description" name="description" rows="3">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (UGX)</label>
                                <input type="number" class="form-control @error('price') is-invalid @enderror" 
                                       id="price" name="price" value="{{ old('price') }}" min="0" step="100" required>
                                @error('price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="duration_hours" class="form-label">Duration (Hours)</label>
                                <input type="number" class="form-control @error('duration_hours') is-invalid @enderror" 
                                       id="duration_hours" name="duration_hours" value="{{ old('duration_hours') }}" 
                                       min="1" placeholder="Leave empty for unlimited">
                                @error('duration_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="data_limit_mb" class="form-label">Data Limit (MB)</label>
                        <input type="number" class="form-control @error('data_limit_mb') is-invalid @enderror" 
                               id="data_limit_mb" name="data_limit_mb" value="{{ old('data_limit_mb') }}" 
                               min="1" placeholder="Leave empty for unlimited">
                        @error('data_limit_mb')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Package is active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Package Modal -->
<div class="modal fade" id="editPackageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editPackageForm">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Package Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_price" class="form-label">Price (UGX)</label>
                                <input type="number" class="form-control" id="edit_price" name="price" min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_duration_hours" class="form-label">Duration (Hours)</label>
                                <input type="number" class="form-control" id="edit_duration_hours" name="duration_hours" min="1">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_data_limit_mb" class="form-label">Data Limit (MB)</label>
                        <input type="number" class="form-control" id="edit_data_limit_mb" name="data_limit_mb" min="1">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">
                                Package is active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function editPackage(packageId) {
    // Fetch package data and populate modal
    fetch(`/hotspots/{{ $hotspot->id }}/packages/${packageId}/edit`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_price').value = data.price;
            document.getElementById('edit_duration_hours').value = data.duration_hours || '';
            document.getElementById('edit_data_limit_mb').value = data.data_limit_mb || '';
            document.getElementById('edit_is_active').checked = data.is_active;
            
            // Update form action
            document.getElementById('editPackageForm').action = `/hotspots/{{ $hotspot->id }}/packages/${packageId}`;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editPackageModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load package data');
        });
}

function deletePackage(packageId) {
    if (confirm('Are you sure you want to delete this package? This action cannot be undone.')) {
        fetch(`/hotspots/{{ $hotspot->id }}/packages/${packageId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete package: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete package');
        });
    }
}
</script>
@endpush
@endsection 