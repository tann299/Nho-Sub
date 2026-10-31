<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Mọi trang dùng layout panel đều có số thông báo chưa đọc cho chuông topbar.
        View::composer('layouts.panel', function ($view) {
            $count = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
            $view->with('unreadCount', $count);
        });
    }
}
