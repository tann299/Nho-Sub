<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Napxu\NapxuClient::class, fn ($app) => new \App\Services\Napxu\NapxuClient(
            $app->make(\App\Services\SettingService::class)->getSecret('napxu_api_key') ?? '',
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dự án dùng design system thuần CSS (không Tailwind) — view phân trang
        // mặc định của Laravel là Tailwind nên SVG mũi tên phình full trang
        // khi bảng có nhiều hơn 1 trang (65 dịch vụ napxu). Chuyển sang markup
        // ul.pagination tự style trong app.css.
        Paginator::defaultView('pagination::default');
        Paginator::defaultSimpleView('pagination::simple-default');
    }
}
