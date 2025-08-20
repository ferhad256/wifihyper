<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;

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
    $plans = \App\Models\SubscriptionPlan::active()->orderBy('sort_order')->get();
    return view('landing', compact('plans'));
})->name('landing');

// Authentication routes with rate limiting
Route::middleware('rate.limiting')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
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
    
    // Voucher management routes
    Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchers/upload', [VoucherController::class, 'upload'])->name('vouchers.upload');
    Route::post('/vouchers/upload-multiple', [VoucherController::class, 'uploadMultiple'])->name('vouchers.upload-multiple');
    Route::post('/vouchers/upload-csv', [VoucherController::class, 'uploadCsv'])->name('vouchers.upload-csv');
    Route::post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
    Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
    Route::delete('/vouchers/delete-all-package', [VoucherController::class, 'deleteAllForPackage'])->name('vouchers.delete-all-package');
    Route::get('/vouchers/export', [VoucherController::class, 'export'])->name('vouchers.export');
    
    // Transaction export route
    Route::get('/transactions/export', [DashboardController::class, 'exportTransactions'])->name('transactions.export');
    
    // Settings routes
    Route::post('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::post('/settings/system', [SettingsController::class, 'updateSystem'])->name('settings.system');
    Route::post('/settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security');
    Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
    
    // Subscription routes
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/subscription/usage', [SubscriptionController::class, 'usage'])->name('subscription.usage');
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::get('/subscription/usage-data', [SubscriptionController::class, 'getUsageData'])->name('subscription.usage-data');
    Route::post('/subscription/check-limit', [SubscriptionController::class, 'checkLimit'])->name('subscription.check-limit');
    Route::post('/subscription/calculate-fee', [SubscriptionController::class, 'calculateTransactionFee'])->name('subscription.calculate-fee');
    Route::post('/subscription/check-feature', [SubscriptionController::class, 'checkFeature'])->name('subscription.check-feature');
    
    // Subscription payment routes
    Route::get('/subscription/payment', [PaymentController::class, 'showSubscriptionPayment'])->name('subscription.payment');
    Route::post('/subscription/payment/initiate', [PaymentController::class, 'initiateSubscriptionPayment'])->name('subscription.payment.initiate');
    Route::post('/subscription/payment/callback', [PaymentController::class, 'subscriptionCallback'])->name('subscription.payment.callback')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::get('/subscription/payment/failed', [PaymentController::class, 'subscriptionFailed'])->name('subscription.payment.failed');
    Route::get('/subscription/payment/success', [PaymentController::class, 'subscriptionSuccess'])->name('subscription.payment.success');
    
    // Profile routes
    Route::put('/profile/update', function() { return back()->with('success', 'Profile updated successfully!'); })->name('profile.update');
    Route::put('/profile/password', function() { return back()->with('success', 'Password changed successfully!'); })->name('profile.password');
    
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
});

// Captive portal routes (for WiFi users)
Route::prefix('portal')->middleware('exclude.notifications')->group(function () {
    Route::get('/{hotspotName}', [PortalController::class, 'index'])->name('portal.index')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::get('/{hotspotName}/payment', [PortalController::class, 'payment'])->name('portal.payment')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::get('/{hotspotName}/inactive', [PortalController::class, 'inactive'])->name('portal.inactive')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::get('/{hotspotName}/test', [PortalController::class, 'test'])->name('portal.test')->where('hotspotName', '[a-zA-Z0-9\-_]+');
    Route::post('/{hotspotName}/check-availability', [PortalController::class, 'checkAvailability'])->name('portal.check-availability')->where('hotspotName', '[a-zA-Z0-9\-_]+');
});

// Payment routes
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/payment/failed', [PaymentController::class, 'failed'])->name('payment.failed')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/payment/status/{transactionId}', [PaymentController::class, 'checkStatus'])->name('payment.status');
Route::get('/payment/pending/{transactionId}', [PaymentController::class, 'pending'])->name('payment.pending');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::post('/payment/redeem', [PaymentController::class, 'redeemVoucher'])->name('payment.redeem');
Route::post('/payment/test', [PaymentController::class, 'testPayment'])->name('payment.test');
