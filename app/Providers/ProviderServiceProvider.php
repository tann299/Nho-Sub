<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ProviderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ProviderManager cần SettingService → dùng singleton để tái sử dụng trong request.
        $this->app->singleton(ProviderManager::class);
    }

    public function boot(): void
    {
        //
    }
}
