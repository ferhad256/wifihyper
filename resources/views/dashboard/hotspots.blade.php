@extends('layouts.dashboard')

@section('title', 'Hotspots Management')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Hotspots Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHotspotModal">
            <i class="fas fa-plus me-2"></i>Add Hotspot
        </button>
    </div>

    <!-- Hotspots Grid -->
    <div class="row">
        @if($hotspots->count() > 0)
            @foreach($hotspots as $hotspot)
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">{{ $hotspot->name }}</h6>
                        <div class="dropdown">
                            <button class="btn btn-link btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('hotspots.show', $hotspot) }}">View Details</a></li>
                                <li><a class="dropdown-item" href="{{ route('hotspots.edit', $hotspot) }}">Edit</a></li>
                                <li><a class="dropdown-item" href="{{ route('hotspots.packages', $hotspot) }}">Manage Packages</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('hotspots.destroy', $hotspot) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure?')">
                                            Delete
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-uppercase mb-1">
                                    SSID: {{ $hotspot->ssid }}
                                </div>
                                <div class="text-xs text-muted mb-2">
                                    {{ $hotspot->location ?? 'No location specified' }}
                                </div>
                                <div class="text-xs text-muted mb-3">
                                    {{ $hotspot->description ?? 'No description' }}
                                </div>
                                
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="text-xs font-weight-bold text-success text-uppercase">
                                            Packages
                                        </div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                                            {{ $hotspot->packages->count() }}
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-xs font-weight-bold text-info text-uppercase">
                                            Status
                                        </div>
                                        <div class="h6 mb-0">
                                            @if($hotspot->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-wifi fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('portal.index', $hotspot->name) }}" class="btn btn-sm btn-primary" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i>View Portal
                        </a>
                        <a href="{{ route('portal.test', $hotspot) }}" class="btn btn-sm btn-info" target="_blank">
                            <i class="fas fa-vial me-1"></i>Test
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        @else
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-wifi fa-3x text-gray-300 mb-3"></i>
                        <h5 class="text-gray-500">No Hotspots Found</h5>
                        <p class="text-gray-500">Get started by creating your first WiFi hotspot.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHotspotModal">
                            <i class="fas fa-plus me-2"></i>Create Your First Hotspot
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Add Hotspot Modal -->
<div class="modal fade" id="addHotspotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Hotspot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('hotspots.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Hotspot Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="ssid" class="form-label">SSID (WiFi Name)</label>
                        <input type="text" class="form-control @error('ssid') is-invalid @enderror" 
                               id="ssid" name="ssid" value="{{ old('ssid') }}" required>
                        @error('ssid')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control @error('location') is-invalid @enderror" 
                               id="location" name="location" value="{{ old('location') }}">
                        @error('location')
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Hotspot</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection 