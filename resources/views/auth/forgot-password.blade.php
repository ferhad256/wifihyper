<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password · WifiHyper</title>
    
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <meta name="theme-color" content="#0A1628">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/brand.css') }}" rel="stylesheet">
    
</head>
<body>
    <div class="wh-auth">
        <div class="wh-auth__inner">
            <div class="wh-auth__brand">
                <x-brand.logo :href="route('landing')" :size="32" tone="light" />
            </div>

            <div class="wh-auth__card">
                <h1 class="wh-auth__title">Forgot your password?</h1>
                <p class="wh-auth__sub">Enter your email and we'll send you a secure reset link.</p>
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email') }}"
                                   placeholder="you@example.com" required autocomplete="email" inputmode="email" autofocus>
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">Send reset link</button>
                </form>

                <div class="wh-auth__foot">
                    Remembered it?
                    <a href="{{ route('login') }}" class="fw-semibold text-decoration-none">Back to sign in</a>
                </div>
            </div>

            <a href="{{ route('landing') }}" class="wh-auth__back">
                <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Back to home
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
