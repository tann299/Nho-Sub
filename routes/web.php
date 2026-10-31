<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceCatalogController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// ---------- Công khai ----------
Route::view('/', 'landing.index')->name('landing');

// ---------- Webhook cổng thanh toán (ngoại lệ CSRF đã khai ở bootstrap) ----------
Route::post('/webhook/sepay', [\App\Http\Controllers\Webhook\SePayController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhook.sepay');

Route::post('/webhook/payos', [\App\Http\Controllers\Webhook\PayOSController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhook.payos');

// ---------- Xác thực ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// ---------- Khu vực khách hàng ----------
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('panel.dashboard');
    Route::get('/settings/password', [ChangePasswordController::class, 'edit'])->name('panel.profile.password');
    Route::post('/settings/password', [ChangePasswordController::class, 'update'])->name('panel.profile.password.update');

    Route::get('/services', [ServiceCatalogController::class, 'index'])->name('panel.services.index');

    Route::get('/orders/create', [OrderController::class, 'create'])->name('panel.orders.create');
    Route::post('/orders/preview', [OrderController::class, 'preview'])->name('panel.orders.preview');
    Route::post('/orders', [OrderController::class, 'store'])->name('panel.orders.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('panel.orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('panel.orders.show')->whereNumber('id');
    Route::post('/orders/{id}/refresh', [OrderController::class, 'refresh'])->name('panel.orders.refresh')->whereNumber('id');

    Route::get('/deposit', [DepositController::class, 'create'])->name('panel.deposit.create');
    Route::post('/deposit', [DepositController::class, 'store'])->name('panel.deposit.store');
    Route::get('/wallet', [WalletController::class, 'index'])->name('panel.wallet.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('panel.profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('panel.profile.update');
    Route::get('/support', [SupportController::class, 'index'])->name('panel.support.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('panel.notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('panel.notifications.readAll');
});

// ---------- Khu vực quản trị ----------
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
});
