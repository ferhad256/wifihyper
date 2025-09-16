# WIFIHYPER Tests

This directory contains the test suite for the WIFIHYPER WiFi billing system.

## Test Structure

### Unit Tests (`tests/Unit/`)
- **SimpleUnitTest.php** - Basic application tests
- **IpnConfigurationTest.php** - IPN configuration validation
- **TransactionIdGenerationTest.php** - Unique transaction ID generation
- **PaymentRetryTest.php** - Payment retry mechanism validation

### Feature Tests (`tests/Feature/`)
- **BasicApplicationTest.php** - Application routing and page accessibility

## Running Tests

### Local Development
```bash
# Run all tests
php artisan test

# Run only unit tests
php artisan test --testsuite=Unit

# Run only feature tests
php artisan test --testsuite=Feature

# Run specific test class
php artisan test --filter=SimpleUnitTest
```

### GitHub Actions
Tests are automatically run on:
- Push to `master`, `main`, or `*.x` branches
- Pull requests to `master` or `main`
- Daily at midnight (UTC)

## Test Configuration

### Environment Variables
Tests use the following configuration:
- `APP_ENV=testing`
- `DB_CONNECTION=sqlite` (in-memory database)
- `CACHE_DRIVER=array`
- `SESSION_DRIVER=array`
- `QUEUE_CONNECTION=sync`
- `MAIL_MAILER=array`
- Test credentials for external services

### Database
Tests use SQLite in-memory database for fast execution and isolation.

### External Services
- Payment services are mocked/disabled during testing
- SMS services use test credentials
- No real API calls are made during tests

## Adding New Tests

### Unit Tests
Create tests in `tests/Unit/` for:
- Service classes
- Helper functions
- Configuration validation
- Business logic

### Feature Tests
Create tests in `tests/Feature/` for:
- HTTP requests
- Page accessibility
- User workflows
- Integration scenarios

## Test Coverage

The test suite aims to cover:
- ✅ Application startup and configuration
- ✅ IPN configuration validation
- ✅ Payment service core functionality
- ✅ Basic routing and page accessibility
- ✅ Environment variable loading
- ✅ Simple transaction ID generation
- ✅ Payment retry mechanism

## Continuous Integration

GitHub Actions workflows:
- **tests.yml** - Full test suite with coverage
- **basic-tests.yml** - Lightweight tests for quick feedback

Both workflows test against PHP 8.2, 8.3, and 8.4. 