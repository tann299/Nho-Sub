<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// ---------- Công khai ----------
Route::view('/', 'landing.index')->name('landing');

// ---------- Webhook cổng thanh toán (ngoại lệ CSRF đã khai ở bootstrap) ----------
Route::post('/webhook/sepay', [\App\Http\Controllers\Webhook\SePayController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhook.sepay');

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
});

// ---------- Khu vực quản trị ----------
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
});
