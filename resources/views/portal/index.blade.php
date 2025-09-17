<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $hotspot->name }} - WiFi Portal</title>
    
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .portal-body {
            padding: 30px;
        }
        .package-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .package-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .btn-buy-now {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-buy-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }
        .wifi-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .loading {
            display: none;
        }
        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .btn-close {
            filter: invert(1);
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-card">
            <div class="portal-header">
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
                @if($hotspot->is_active)
                    @if($packages->count() > 0)
                        <h5 class="text-center mb-4">Choose Your WiFi Package</h5>
                        
                        <div class="packages-container">
                            @foreach($packages as $package)
                            <div class="package-card" data-package-id="{{ $package->id }}">
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
                                        
                                        <!-- Stock status indicator with count -->
                                        <div class="stock-status mt-2">
                                            @if($package->is_out_of_stock)
                                                <small class="text-danger">
                                                    <i class="fas fa-times-circle me-1"></i>Out of Stock
                                                </small>
                                            @else
                                                <small class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i>Available
                                                </small>
                                            @endif
                                            
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="h5 mb-2 text-primary">UGX {{ number_format($package->price) }}</div>
                                        @if($package->has_vouchers)
                                            <button type="button" class="btn btn-buy-now" 
                                                    onclick="openPaymentModal({{ $package->id }}, '{{ $package->name }}', {{ $package->price }})"
                                                    data-package-id="{{ $package->id }}">
                                                <i class="fas fa-shopping-cart me-1"></i>Buy Now
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-secondary" disabled>
                                                <i class="fas fa-times-circle me-1"></i>Out of Stock
                                            </button>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-exclamation-triangle me-1"></i>No vouchers available
                                            </small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>Secure payment via JPesa
                            </small>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5>No Packages Available</h5>
                            <p class="text-muted">This hotspot doesn't have any packages configured yet.</p>
                        </div>
                    @endif
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-wifi fa-3x text-muted mb-3"></i>
                        <h5>Hotspot Inactive</h5>
                        <p class="text-muted">This WiFi hotspot is currently inactive.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card me-2"></i>Complete Payment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('payment.initiate') }}" id="paymentForm">
                    @csrf
                    <input type="hidden" name="hotspot_id" value="{{ $hotspot->id }}">
                    <input type="hidden" name="package_id" id="modal_package_id">
                    
                    <div class="modal-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Payment Error:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                        @if(session('error'))
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Payment Error:</strong> {{ session('error') }}
                            </div>
                        @endif
                        
                        <div class="text-center mb-4">
                            <h6 id="modal_package_name" class="text-primary"></h6>
                            <div class="h4 text-success" id="modal_package_price"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_phone_number" class="form-label">
                                <i class="fas fa-phone me-1"></i>Phone Number
                            </label>
                            <input type="tel" class="form-control" id="modal_phone_number" name="phone_number" 
                                   placeholder="Enter your phone number (e.g., 0744744888, 0397373763)" 
                                   value="{{ old('phone_number') }}" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                We'll send your WiFi voucher code to this number via SMS.
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Payment Process:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Enter your phone number</li>
                                <li>Complete payment via JPesa</li>
                                <li>Receive WiFi voucher code via SMS</li>
                                <li>Use the code to connect to WiFi</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success" id="processPaymentBtn">
                            <span class="btn-text">
                                <i class="fas fa-credit-card me-1"></i>Process Payment
                            </span>
                            <span class="loading">
                                <i class="fas fa-spinner fa-spin me-1"></i>Processing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let paymentModal;
        let availabilityCheckInterval;
        
        // Real-time availability checking with enhanced frequency
        function checkAvailability(packageId) {
            fetch(`{{ route('portal.check-availability', $hotspot->url_name) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    package_id: packageId
                })
            })
            .then(response => response.json())
            .then(data => {
                updatePackageAvailability(packageId, data);
            })
            .catch(error => {
                console.error('Error checking availability:', error);
            });
        }
        
        function updatePackageAvailability(packageId, availability) {
            const packageCard = document.querySelector(`[data-package-id="${packageId}"]`);
            if (!packageCard) return;
            
            const buyButton = packageCard.querySelector('.btn-buy-now');
            const stockStatus = packageCard.querySelector('.stock-status');
            
            // Update stock status
            if (stockStatus) {
                if (!availability.has_vouchers) {
                    stockStatus.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Out of Stock</small>';
                } else {
                    stockStatus.innerHTML = '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Available</small>';
                }
            }
            
            // Update buy button
            if (buyButton) {
                if (!availability.has_vouchers) {
                    buyButton.disabled = true;
                    buyButton.className = 'btn btn-secondary';
                    buyButton.innerHTML = '<i class="fas fa-times-circle me-1"></i>Out of Stock';
                    buyButton.onclick = null;
                } else {
                    buyButton.disabled = false;
                    buyButton.className = 'btn btn-buy-now';
                    buyButton.innerHTML = '<i class="fas fa-shopping-cart me-1"></i>Buy Now';
                    buyButton.onclick = function() {
                        openPaymentModal(packageId, packageCard.querySelector('h6').textContent, 
                                      parseInt(packageCard.querySelector('.text-primary').textContent.replace(/[^\d]/g, '')));
                    };
                }
            }
        }
        
        // Check availability for all packages with increased frequency
        function startAvailabilityChecking() {
            const packageIds = Array.from(document.querySelectorAll('[data-package-id]'))
                .map(card => card.getAttribute('data-package-id'));
            
            // Check availability every 10 seconds for real-time updates
            availabilityCheckInterval = setInterval(() => {
                packageIds.forEach(packageId => {
                    checkAvailability(packageId);
                });
            }, 10000);
            
            // Also check immediately when page loads
            packageIds.forEach(packageId => {
                checkAvailability(packageId);
            });
        }
        
        function openPaymentModal(packageId, packageName, packagePrice) {
            // Check availability before opening modal
            checkAvailability(packageId);
            
            // Set modal content
            document.getElementById('modal_package_id').value = packageId;
            document.getElementById('modal_package_name').textContent = packageName;
            document.getElementById('modal_package_price').textContent = 'UGX ' + packagePrice.toLocaleString();
            
            // Clear previous phone number
            document.getElementById('modal_phone_number').value = '';
            
            // Show modal
            paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        }
        
        // Form submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            let phoneNumber = document.getElementById('modal_phone_number').value;
            
            if (!phoneNumber) {
                e.preventDefault();
                alert('Please enter your phone number.');
                return;
            }
            
            // Convert phone number to international format
            phoneNumber = convertToInternationalFormat(phoneNumber);
            
            // Validate phone number format (Uganda international format)
            const phoneRegex = /^256[0-9]{9}$/;
            if (!phoneRegex.test(phoneNumber)) {
                e.preventDefault();
                alert('Please enter a valid Uganda phone number (e.g., 0744744888, 0397373763).');
                return;
            }
            
            // Update the form field with the converted number
            document.getElementById('modal_phone_number').value = phoneNumber;
            
            // Show loading state
            document.querySelector('.btn-text').style.display = 'none';
            document.querySelector('.loading').style.display = 'inline';
            document.getElementById('processPaymentBtn').disabled = true;
        });
        
        // Function to convert phone number to international format
        function convertToInternationalFormat(phoneNumber) {
            // Remove all non-digit characters
            let cleanNumber = phoneNumber.replace(/\D/g, '');
            
            // If number starts with 0, remove it and add 256
            if (cleanNumber.startsWith('0')) {
                cleanNumber = '256' + cleanNumber.substring(1);
            }
            // If number doesn't start with 256, add it
            else if (!cleanNumber.startsWith('256')) {
                cleanNumber = '256' + cleanNumber;
            }
            
            // Ensure the final number is exactly 12 digits (256 + 9 digits)
            if (cleanNumber.length > 12) {
                cleanNumber = cleanNumber.substring(0, 12);
            }
            
            return cleanNumber;
        }
        
        // Phone number input formatting (show user-friendly format)
        document.getElementById('modal_phone_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Limit to 10 digits for local format (e.g., 0744744888)
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            // Format as local number (e.g., 0744744888)
            e.target.value = value;
        });
        
        // Start availability checking when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startAvailabilityChecking();
        });
        
        // Clean up interval when page unloads
        window.addEventListener('beforeunload', function() {
            if (availabilityCheckInterval) {
                clearInterval(availabilityCheckInterval);
            }
        });
    </script>
</body>
</html> 