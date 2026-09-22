<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\WithdrawalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The operator dashboard and the admin console are Filament panels, mounted
| at /dashboard and /admin by their panel providers - they are not listed
| here. What remains is the public marketing page, the captive portal that
| WiFi customers use, the payment callbacks, and the account flows that stay
| outside the panels.
|
*/

// Landing page
Route::get('/', function () {
    return view('landing');
})->name('landing');

/*
|--------------------------------------------------------------------------
| Account access
|--------------------------------------------------------------------------
|
| Signing in is handled by the tenant panel. /login is kept as a redirect so
| existing bookmarks, and the many route('login') references in middleware
| and controllers, keep working.
|
| Registration and email verification stay here rather than moving into the
| panel: registration deliberately creates an inactive, unverified tenant,
| whereas Filament's own registration signs the new user straight in - which
| canAccessPanel() would then refuse.
|
*/
Route::get('/login', fn () => redirect()->route('filament.tenant.auth.login'))->name('login');

Route::middleware('rate.limiting')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Password reset
    Route::get('/forgot-password', [App\Http\Controllers\PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [App\Http\Controllers\PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [App\Http\Controllers\PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [App\Http\Controllers\PasswordResetController::class, 'reset'])->name('password.update');
});

// Email verification (a code flow, not a signed-link flow)
Route::get('/verify-email', [App\Http\Controllers\EmailVerificationController::class, 'show'])->name('verification.show');
Route::post('/verify-email', [App\Http\Controllers\EmailVerificationController::class, 'verify'])->name('verification.verify');
Route::post('/verify-email/resend', [App\Http\Controllers\EmailVerificationController::class, 'resend'])->name('verification.resend');
Route::get('/verify-email/status', [App\Http\Controllers\EmailVerificationController::class, 'status'])->name('verification.status');

/*
|--------------------------------------------------------------------------
| Old dashboard URLs
|--------------------------------------------------------------------------
|
| The Blade dashboard lived at these paths for the life of the product, so
| they are redirected rather than dropped. /dashboard itself needs no
| redirect - the tenant panel now answers there.
|
*/
Route::permanentRedirect('/hotspots', '/dashboard/hotspots');
Route::permanentRedirect('/vouchers', '/dashboard/vouchers');
Route::permanentRedirect('/billing', '/dashboard/transactions');
Route::permanentRedirect('/profile', '/dashboard/account');
Route::permanentRedirect('/settings', '/dashboard/account');
Route::permanentRedirect('/withdrawal', '/dashboard/withdrawal-transactions');

/*
|--------------------------------------------------------------------------
| Captive portal
|--------------------------------------------------------------------------
|
| What a WiFi customer sees. Deliberately outside the panels: these screens
| run on a venue's own network, on whatever handset walks in, and must stay
| as light as possible.
|
*/
Route::prefix('portal')->middleware('exclude.notifications')->group(function () {
    Route::get('/{hotspotName}', [PortalController::class, 'index'])->name('portal.index')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::get('/{hotspotName}/payment', [PortalController::class, 'payment'])->name('portal.payment')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::get('/{hotspotName}/inactive', [PortalController::class, 'inactive'])->name('portal.inactive')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::post('/{hotspotName}/check-availability', [PortalController::class, 'checkAvailability'])->name('portal.check-availability')->where('hotspotName', '[a-zA-Z0-9\-_]+');
});

// Payment status screens (polled by the portal while a payment settles)
Route::prefix('payment')->middleware('exclude.notifications')->group(function () {
    Route::get('/pending/{transactionId}', [PortalController::class, 'pending'])->name('payment.pending');
    Route::get('/success', [PortalController::class, 'success'])->name('payment.success');
    Route::get('/failed', [PortalController::class, 'failed'])->name('payment.failed');
    Route::post('/check-status', [PortalController::class, 'checkTransactionStatus'])->name('payment.check-status');
});

// Payment flow
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::get('/payment/status/{transactionId}', [PaymentController::class, 'checkStatus'])->name('payment.status');
Route::post('/payment/redeem-voucher', [PaymentController::class, 'redeemVoucher'])->name('payment.redeem-voucher');

/*
|--------------------------------------------------------------------------
| Gateway callbacks
|--------------------------------------------------------------------------
|
| Called by JPesa and Resend, not by a browser, so they are exempt from CSRF.
|
*/
Route::post('/payment/jpesa/callback', [PaymentController::class, 'jpesaCallback'])
    ->name('payment.jpesa.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/withdrawal/callback', [WithdrawalController::class, 'handleCallback'])
    ->name('withdrawal.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhooks/resend', [App\Http\Controllers\ResendWebhookController::class, 'handle'])
    ->name('webhooks.resend')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/webhooks/resend/stats', [App\Http\Controllers\ResendWebhookController::class, 'stats'])
    ->name('webhooks.resend.stats');

// Readiness probe for the JPesa callback endpoint
Route::get('/payment/jpesa/test-callback', function () {
    return response()->json([
        'status' => 'callback_endpoint_ready',
        'url' => route('payment.jpesa.callback'),
        'method' => 'POST',
        'timestamp' => now()->toISOString(),
    ]);
})->name('payment.jpesa.test-callback');
