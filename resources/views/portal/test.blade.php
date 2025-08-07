<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Portal - {{ $hotspot->name }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .portal-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .portal-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
        }
        .portal-header {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .portal-body {
            padding: 30px;
        }
        .test-badge {
            background: #ffc107;
            color: #000;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-card">
            <div class="portal-header">
                <div class="test-badge mb-2">TEST MODE</div>
                <div class="wifi-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <h3>{{ $hotspot->name }}</h3>
                <p class="mb-0">{{ $hotspot->ssid }}</p>
                @if($hotspot->location)
                    <small><i class="fas fa-map-marker-alt me-1"></i>{{ $hotspot->location }}</small>
                @endif
            </div>
            
            <div class="portal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Test Portal</strong> - This is a development/testing view for the WiFi portal.
                </div>
                
                <h5 class="mb-3">Available Packages</h5>
                
                @if($packages->count() > 0)
                    @foreach($packages as $package)
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h6 class="mb-1">{{ $package->name }}</h6>
                                    <p class="text-muted mb-1">{{ $package->description }}</p>
                                    @if($package->duration_hours)
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>{{ $package->duration_hours }} hours
                                        </small>
                                    @endif
                                    @if($package->data_limit_mb)
                                        <small class="text-muted ms-2">
                                            <i class="fas fa-database me-1"></i>{{ $package->data_limit_mb }}MB
                                        </small>
                                    @endif
                                </div>
                                <div class="col-4 text-end">
                                    <div class="h5 mb-0 text-primary">UGX {{ number_format($package->price) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    
                    <div class="mt-4">
                        <a href="{{ route('portal.index', $hotspot->url_name) }}" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-external-link-alt me-2"></i>View Live Portal
                        </a>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>No Packages Available</h5>
                        <p class="text-muted">This hotspot doesn't have any packages configured yet.</p>
                    </div>
                @endif
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="fas fa-code me-1"></i>Portal URL: /portal/{{ $hotspot->id }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 