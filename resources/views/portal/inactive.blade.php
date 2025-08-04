<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotspot Inactive - WiFi Portal</title>
    
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
            max-width: 500px;
            width: 100%;
        }
        .portal-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .portal-body {
            padding: 30px;
        }
        .inactive-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-card">
            <div class="portal-header">
                <div class="inactive-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <h3>{{ $hotspot->name ?? 'WiFi Hotspot' }}</h3>
                <p class="mb-0">{{ $hotspot->ssid ?? 'WiFi Network' }}</p>
                @if($hotspot->location)
                    <small><i class="fas fa-map-marker-alt me-1"></i>{{ $hotspot->location }}</small>
                @endif
            </div>
            
            <div class="portal-body">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>Hotspot Currently Inactive</h5>
                        <p class="text-muted">This WiFi hotspot is temporarily unavailable for service.</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Why is this happening?</strong>
                        <ul class="mb-0 mt-2 text-start">
                            <li>The hotspot may be under maintenance</li>
                            <li>Service has been temporarily suspended</li>
                            <li>Please try again later</li>
                            <li>Contact the hotspot owner for support</li>
                        </ul>
                    </div>
                    
                    <div class="mt-4">
                        <button class="btn btn-primary btn-lg" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Try Again
                        </button>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>Last updated: {{ now()->format('M d, Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 