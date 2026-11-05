<?php

use App\Http\Controllers\Admin\AdminApiLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDepositController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPostController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWebhookController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceCatalogController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// ---------- Công khai ----------
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Nội dung công khai (bài viết, điều khoản, FAQ)
Route::get('/posts', [ContentController::class, 'posts'])->name('content.posts');
Route::get('/posts/{slug}', [ContentController::class, 'post'])->name('content.post')->where('slug', '[a-z0-9\-]+');
Route::get('/terms', [ContentController::class, 'terms'])->name('content.terms');
Route::get('/faq', [ContentController::class, 'faq'])->name('content.faq');

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
    Route::get('/deposit/status', [DepositController::class, 'status'])->name('panel.deposit.status');
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

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/adjust', [AdminUserController::class, 'adjust'])->name('users.adjust');
    Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggle'])->name('users.toggle');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');
    Route::post('/orders/{order}/resolve-stuck', [AdminOrderController::class, 'resolveStuck'])->name('orders.resolveStuck');

    Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
    Route::post('/services/sync', [AdminServiceController::class, 'sync'])->name('services.sync');
    Route::post('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');

    Route::get('/deposits', [AdminDepositController::class, 'index'])->name('deposits.index');
    Route::post('/deposits/{deposit}/approve', [AdminDepositController::class, 'approve'])->name('deposits.approve');
    Route::post('/deposits/{deposit}/reject', [AdminDepositController::class, 'reject'])->name('deposits.reject');

    Route::get('/webhooks', [AdminWebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks/{webhook}/credit', [AdminWebhookController::class, 'credit'])->name('webhooks.credit');

    Route::get('/posts', [AdminPostController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [AdminPostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [AdminPostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [AdminPostController::class, 'edit'])->name('posts.edit');
    Route::post('/posts/{post}', [AdminPostController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/pin', [AdminPostController::class, 'pin'])->name('posts.pin');
    Route::post('/posts/{post}/delete', [AdminPostController::class, 'destroy'])->name('posts.destroy');

    Route::get('/api-logs', [AdminApiLogController::class, 'index'])->name('apiLogs.index');

    Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
});
