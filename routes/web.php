<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WithdrawalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Landing page
Route::get('/', function () {
    return view('landing');
})->name('landing');

// Authentication routes with rate limiting
Route::middleware('rate.limiting')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    
    // Password reset routes
    Route::get('/forgot-password', [App\Http\Controllers\PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [App\Http\Controllers\PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [App\Http\Controllers\PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [App\Http\Controllers\PasswordResetController::class, 'reset'])->name('password.update');
});

// Email verification routes
Route::get('/verify-email', [App\Http\Controllers\EmailVerificationController::class, 'show'])->name('verification.show');
Route::post('/verify-email', [App\Http\Controllers\EmailVerificationController::class, 'verify'])->name('verification.verify');
Route::post('/verify-email/resend', [App\Http\Controllers\EmailVerificationController::class, 'resend'])->name('verification.resend');
Route::get('/verify-email/status', [App\Http\Controllers\EmailVerificationController::class, 'status'])->name('verification.status');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');



// Dashboard routes
Route::middleware('auth.tenant')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/withdraw', [DashboardController::class, 'withdraw'])->name('dashboard.withdraw');
    Route::get('/billing', [DashboardController::class, 'billing'])->name('dashboard.billing');
    Route::get('/hotspots', [HotspotController::class, 'index'])->name('dashboard.hotspots');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('dashboard.settings');
    Route::get('/profile', [DashboardController::class, 'profile'])->name('dashboard.profile');
    Route::get('/export-transactions', [DashboardController::class, 'exportTransactions'])->name('dashboard.export-transactions');
    
    // Notification routes
    Route::post('/notifications/mark-read', [DashboardController::class, 'markNotificationAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [DashboardController::class, 'markAllNotificationsAsRead'])->name('notifications.mark-all-read');
    Route::get('/notifications/count', [DashboardController::class, 'getUnreadNotificationsCount'])->name('notifications.count');
    Route::get('/notifications', [DashboardController::class, 'getNotifications'])->name('notifications.get');
    
    // Support: Request a call
    Route::post('/support/request-call', [DashboardController::class, 'requestSupportCall'])->name('support.request-call');
    
    // Voucher management routes
    Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchers/upload', [VoucherController::class, 'upload'])->name('vouchers.upload');
    Route::post('/vouchers/upload-multiple', [VoucherController::class, 'uploadMultiple'])->name('vouchers.upload-multiple');
    Route::post('/vouchers/upload-csv', [VoucherController::class, 'uploadCsv'])->name('vouchers.upload-csv');
    Route::post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
    Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
    Route::delete('/vouchers/delete-all-package', [VoucherController::class, 'deleteAllForPackage'])->name('vouchers.delete-all-package');
    Route::delete('/vouchers/delete-all-hotspot', [VoucherController::class, 'deleteAllForHotspot'])->name('vouchers.delete-all-hotspot');
    Route::get('/vouchers/export', [VoucherController::class, 'export'])->name('vouchers.export');
    
    // Transaction export route
    Route::get('/transactions/export', [DashboardController::class, 'exportTransactions'])->name('transactions.export');
    
    // Settings routes
    Route::post('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::post('/settings/system', [SettingsController::class, 'updateSystem'])->name('settings.system');
    Route::post('/settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security');
    Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
    
    
    // Profile routes
    Route::put('/profile/update', [App\Http\Controllers\ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'changePassword'])->name('profile.password');
    Route::get('/profile/delete', [App\Http\Controllers\ProfileController::class, 'showDeleteConfirmation'])->name('profile.delete');
    Route::delete('/profile/delete', [App\Http\Controllers\ProfileController::class, 'deleteAccount'])->name('profile.delete.confirm');
    
    // Hotspot management routes
    Route::post('/hotspots', [HotspotController::class, 'store'])->name('hotspots.store');
    Route::get('/hotspots/{hotspot}', [HotspotController::class, 'show'])->name('hotspots.show');
    Route::get('/hotspots/{hotspot}/edit', [HotspotController::class, 'edit'])->name('hotspots.edit');
    Route::put('/hotspots/{hotspot}', [HotspotController::class, 'update'])->name('hotspots.update');
    Route::delete('/hotspots/{hotspot}', [HotspotController::class, 'destroy'])->name('hotspots.destroy');
    
    // Package management routes
    Route::get('/hotspots/{hotspot}/packages', [HotspotController::class, 'packages'])->name('hotspots.packages');
    Route::post('/hotspots/{hotspot}/packages', [HotspotController::class, 'storePackage'])->name('hotspots.packages.store');
    Route::get('/hotspots/{hotspot}/packages/{package}/edit', [HotspotController::class, 'editPackage'])->name('hotspots.packages.edit');
    Route::put('/hotspots/{hotspot}/packages/{package}', [HotspotController::class, 'updatePackage'])->name('hotspots.packages.update');
    Route::delete('/hotspots/{hotspot}/packages/{package}', [HotspotController::class, 'deletePackage'])->name('hotspots.packages.destroy');
    
    // Withdrawals are requested via DashboardController::withdraw (the
    // dashboard.withdraw route above). WithdrawalController's own screens were
    // removed: they authenticated against the wrong guard and rendered views
    // that were never created. Only its JPesa callback remains, registered
    // outside this group.
});

// Captive portal routes (for WiFi users)
Route::prefix('portal')->middleware('exclude.notifications')->group(function () {
    Route::get('/{hotspotName}', [PortalController::class, 'index'])->name('portal.index')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::get('/{hotspotName}/payment', [PortalController::class, 'payment'])->name('portal.payment')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::get('/{hotspotName}/inactive', [PortalController::class, 'inactive'])->name('portal.inactive')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::post('/{hotspotName}/check-availability', [PortalController::class, 'checkAvailability'])->name('portal.check-availability')->where('hotspotName', '[a-zA-Z0-9\-_]+');
});

// Payment status routes (for real-time status checking)
Route::prefix('payment')->middleware('exclude.notifications')->group(function () {
    Route::get('/pending/{transactionId}', [PortalController::class, 'pending'])->name('payment.pending');
    Route::get('/success', [PortalController::class, 'success'])->name('payment.success');
    Route::get('/failed', [PortalController::class, 'failed'])->name('payment.failed');
    Route::post('/check-status', [PortalController::class, 'checkTransactionStatus'])->name('payment.check-status');
});

// Payment routes
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::get('/payment/status/{transactionId}', [PaymentController::class, 'checkStatus'])->name('payment.status');
Route::post('/payment/redeem-voucher', [PaymentController::class, 'redeemVoucher'])->name('payment.redeem-voucher');
Route::get('/payment/pending/{transactionId}', [PaymentController::class, 'pending'])->name('payment.pending');
// NOTE: a duplicate `/payment/failed` route pointed at PaymentController@failed,
// which does not exist. Registered after the group above, it shadowed the working
// PortalController@failed and made every failed payment throw BadMethodCallException.
// Removed so the failure screen resolves again.

// Unified IPN endpoint for all payment responses (success, failure, pending)
Route::post('/payment/ipn', [PaymentController::class, 'unifiedIpn'])
    ->name('payment.ipn')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// JPesa callback route
Route::post('/payment/jpesa/callback', [PaymentController::class, 'jpesaCallback'])
    ->name('payment.jpesa.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Withdrawal callback route (excluded from CSRF)
Route::post('/withdrawal/callback', [WithdrawalController::class, 'handleCallback'])
    ->name('withdrawal.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// JPesa callback test route (for testing purposes)
Route::get('/payment/jpesa/test-callback', function() {
    return response()->json([
        'status' => 'callback_endpoint_ready',
        'url' => route('payment.jpesa.callback'),
        'method' => 'POST',
        'timestamp' => now()->toISOString(),
        'message' => 'JPesa callback endpoint is ready to receive callbacks'
    ]);
})->name('payment.jpesa.test-callback');

// Resend webhook routes (excluded from CSRF)
Route::post('/webhooks/resend', [App\Http\Controllers\ResendWebhookController::class, 'handle'])->name('webhooks.resend')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/webhooks/resend/stats', [App\Http\Controllers\ResendWebhookController::class, 'stats'])->name('webhooks.resend.stats')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Admin authentication routes (no middleware)
    Route::get('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'login']);
    Route::post('/logout', [App\Http\Controllers\Admin\AdminAuthController::class, 'logout'])->name('logout');
    
    // Protected admin routes
    Route::middleware(['auth.admin'])->group(function () {
        // Dashboard
        Route::get('/', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index']);
        
        // Tenant management
        Route::get('/tenants', [App\Http\Controllers\Admin\AdminDashboardController::class, 'tenants'])->name('tenants');
        Route::get('/tenants/{id}', [App\Http\Controllers\Admin\AdminDashboardController::class, 'showTenant'])->name('tenants.show');
        Route::post('/tenants/{id}/toggle-status', [App\Http\Controllers\Admin\AdminDashboardController::class, 'toggleTenantStatus'])->name('tenants.toggle-status');
        Route::delete('/tenants/{id}', [App\Http\Controllers\Admin\AdminDashboardController::class, 'deleteTenant'])->name('tenants.destroy');
        
        // Withdrawal management
        Route::get('/withdrawals', [App\Http\Controllers\Admin\AdminDashboardController::class, 'withdrawals'])->name('withdrawals');
        Route::post('/withdrawals/{id}/approve', [App\Http\Controllers\Admin\AdminDashboardController::class, 'approveWithdrawal'])->name('withdrawals.approve');
        Route::post('/withdrawals/{id}/reject', [App\Http\Controllers\Admin\AdminDashboardController::class, 'rejectWithdrawal'])->name('withdrawals.reject');
        
        // Transaction monitoring
        Route::get('/transactions', [App\Http\Controllers\Admin\AdminDashboardController::class, 'transactions'])->name('transactions');
        
        // Admin profile management
        Route::get('/profile', [App\Http\Controllers\Admin\AdminDashboardController::class, 'profile'])->name('profile');
        Route::put('/profile', [App\Http\Controllers\Admin\AdminDashboardController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/password', [App\Http\Controllers\Admin\AdminDashboardController::class, 'changePassword'])->name('profile.password');
        
        // Super admin only routes
        Route::middleware(['auth.super_admin'])->group(function () {
            Route::get('/register', [App\Http\Controllers\Admin\AdminAuthController::class, 'showRegister'])->name('register');
            Route::post('/register', [App\Http\Controllers\Admin\AdminAuthController::class, 'register']);
        });
    });
});
