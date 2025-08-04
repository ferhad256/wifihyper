<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SettingsController;

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

// Authentication routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
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
    Route::get('/{hotspot}', [PortalController::class, 'index'])->name('portal.index');
    Route::get('/{hotspot}/payment', [PortalController::class, 'payment'])->name('portal.payment');
    Route::get('/{hotspot}/inactive', [PortalController::class, 'inactive'])->name('portal.inactive');
    Route::get('/{hotspot}/test', [PortalController::class, 'test'])->name('portal.test');
    Route::post('/{hotspot}/check-availability', [PortalController::class, 'checkAvailability'])->name('portal.check-availability');
});

// Payment routes
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::post('/payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
Route::get('/payment/status/{transactionId}', [PaymentController::class, 'checkStatus'])->name('payment.status');
Route::get('/payment/pending/{transactionId}', [PaymentController::class, 'pending'])->name('payment.pending');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::post('/payment/redeem', [PaymentController::class, 'redeemVoucher'])->name('payment.redeem');
Route::post('/payment/test', [PaymentController::class, 'testPayment'])->name('payment.test');
