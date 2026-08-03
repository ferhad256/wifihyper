<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in · WifiHyper</title>

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
                <h1 class="wh-auth__title">Welcome back</h1>
                <p class="wh-auth__sub">Sign in to your account.</p>
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

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope" aria-hidden="true"></i>
                            </span>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email') }}" required
                                   autocomplete="email" inputmode="email" autofocus>
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock" aria-hidden="true"></i>
                            </span>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password" required autocomplete="current-password">
                            <button class="btn btn-secondary" type="button" id="togglePassword"
                                    aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" id="togglePasswordIcon" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 d-flex justify-content-between align-items-center gap-2">
                        <div class="form-check mb-0">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="{{ route('password.request') }}" class="text-decoration-none small">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
                </form>

                <div class="wh-auth__foot">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="fw-semibold text-decoration-none">Create one</a>
                </div>
            </div>

            <a href="{{ route('landing') }}" class="wh-auth__back">
                <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Back to home
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password visibility toggle. The button's label and pressed state are
        // updated too, so a screen reader reports what it currently does.
        document.getElementById('togglePassword').addEventListener('click', function () {
            const field = document.getElementById('password');
            const icon  = document.getElementById('togglePasswordIcon');
            const shown = field.type === 'text';

            field.type = shown ? 'password' : 'text';
            icon.classList.toggle('fa-eye', shown);
            icon.classList.toggle('fa-eye-slash', !shown);
            this.setAttribute('aria-pressed', String(!shown));
            this.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
        });
    </script>
</body>
</html> 