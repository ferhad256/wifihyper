<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Tenant WiFi Management Platform</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/logo.png') }}">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        .cta-section {
            background: #f8f9fa;
            padding: 80px 0;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        .footer {
            background: #2c3e50;
            color: white;
            padding: 40px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                <img src="{{ asset('images/logo/logo.png') }}" alt="Logo" style="height: 50px; width: auto; max-width: 180px;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-primary me-2" href="{{ route('login') }}">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="{{ route('register') }}">Sign Up</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">
                        Manage Your WiFi Hotspots with Ease
                    </h1>
                    <p class="lead mb-4">
                        A comprehensive platform for managing multiple WiFi hotspots, 
                        voucher systems, and payment processing. Perfect for cafes, hotels, 
                        and businesses.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="{{ route('register') }}" class="btn btn-light btn-lg">
                            <i class="fas fa-rocket me-2"></i>Get Started Free
                        </a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-play me-2"></i>Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-wifi" style="font-size: 200px; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Powerful Features</h2>
                <p class="lead text-muted">Everything you need to manage your WiFi business</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h4>Voucher Management</h4>
                        <p class="text-muted">
                            Upload and manage voucher codes in bulk. Support for CSV imports, 
                            manual entry, and automatic voucher generation.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Sales Analytics</h4>
                        <p class="text-muted">
                            Real-time analytics and reporting. Track sales, monitor usage, 
                            and generate detailed reports for your business insights.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="fas fa-sms"></i>
                        </div>
                        <h4>SMS Integration</h4>
                        <p class="text-muted">
                            Automatic SMS delivery of voucher codes. Integrated with 
                            popular SMS gateways for reliable message delivery.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">How It Works</h2>
                <p class="lead text-muted">Simple steps to get your WiFi business running</p>
            </div>
            <div class="row g-4">
                <div class="col-md-3 text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                        1
                    </div>
                    <h5 class="mt-3">Sign Up</h5>
                    <p class="text-muted">Create your account and set up your business profile</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                        2
                    </div>
                    <h5 class="mt-3">Add Hotspots</h5>
                    <p class="text-muted">Configure your WiFi hotspots and create packages</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                        3
                    </div>
                    <h5 class="mt-3">Upload Vouchers</h5>
                    <p class="text-muted">Upload voucher codes and start accepting payments</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                        4
                    </div>
                    <h5 class="mt-3">Start Earning</h5>
                    <p class="text-muted">Monitor sales and grow your WiFi business</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Simple Pricing</h2>
                <p class="lead text-muted">Choose the plan that fits your business</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <h4>Basic</h4>
                            <div class="display-6 fw-bold text-primary mb-3">$29<span class="fs-6 text-muted">/month</span></div>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Up to 5 hotspots</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>1,000 vouchers/month</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Basic analytics</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Email support</li>
                            </ul>
                            <a href="{{ route('register') }}" class="btn btn-primary w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 border-primary">
                        <div class="card-body text-center p-4">
                            <span class="badge bg-primary mb-2">Most Popular</span>
                            <h4>Professional</h4>
                            <div class="display-6 fw-bold text-primary mb-3">$79<span class="fs-6 text-muted">/month</span></div>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Up to 20 hotspots</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>10,000 vouchers/month</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Advanced analytics</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Priority support</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>SMS integration</li>
                            </ul>
                            <a href="{{ route('register') }}" class="btn btn-primary w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <h4>Enterprise</h4>
                            <div class="display-6 fw-bold text-primary mb-3">$199<span class="fs-6 text-muted">/month</span></div>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Unlimited hotspots</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Unlimited vouchers</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Custom analytics</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>24/7 support</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>API access</li>
                            </ul>
                            <a href="{{ route('register') }}" class="btn btn-primary w-100">Get Started</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="display-5 fw-bold mb-4">Ready to Start Your WiFi Business?</h2>
                                <p class="lead mb-4">Join thousands of businesses already using our platform to manage their hotspots</p>
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-rocket me-2"></i>Start Your Free Trial
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-3">
                        <img src="{{ asset('images/logo/logo.png') }}" alt="Logo" style="height: 40px; width: auto; max-width: 150px;">
                    </div>
                    <p class="text-muted">The complete solution for managing WiFi hotspots and voucher systems.</p>
                </div>
                <div class="col-md-3">
                    <h6>Product</h6>
                    <ul class="list-unstyled">
                        <li><a href="#features" class="text-muted text-decoration-none">Features</a></li>
                        <li><a href="#pricing" class="text-muted text-decoration-none">Pricing</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Documentation</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">About</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Contact</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Support</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0">&copy; 2024 All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-muted text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 