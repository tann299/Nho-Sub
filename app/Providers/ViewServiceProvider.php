<?php

namespace App\Providers;

use App\Http\Controllers\ContentController;
use App\Models\Post;
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

        // Submenu "Sản phẩm & dịch vụ": danh mục đang bán, nhóm theo nền tảng.
        View::composer('partials.sidebar-menu', function ($view) {
            $view->with('menuCategories', ContentController::menuCategories());
        });

        // Banner thông báo mới nhất trên dashboard (tối đa 3 bài) + thông báo hệ thống.
        View::composer('panel.dashboard', function ($view) {
            $view->with('latestPosts', Post::published()->limit(3)->get(['id', 'title', 'slug', 'published_at']));

            $user = auth()->user();

            $view->with('notifications', $user
                ? $user->notifications()->limit(4)->get()
                : collect());
        });
    }
}
