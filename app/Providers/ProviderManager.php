<?php

namespace App\Providers;

use App\Providers\Demo\DemoProvider;
use App\Providers\Napxu\NapxuProvider;
use App\Providers\TopSubRe\TopSubReProvider;
use App\Services\PricingService;
use App\Services\SettingService;
use InvalidArgumentException;

/**
 * Chọn nhà cung cấp đang dùng.
 *  - demo_mode = 'on'     → Demo (bắt buộc, dù có key)
 *  - demo_mode = 'off'    → NCC đã chọn trong cấu hình (mặc định TopSubRe)
 *  - demo_mode = 'auto'   → NCC đã chọn nếu có key, ngược lại Demo
 *
 * NCC chọn bằng setting `provider_driver`: 'topsubre' (mặc định) | 'napxu'.
 */
class ProviderManager
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly PricingService $pricing,
    ) {
    }

    public function driver(): ProviderInterface
    {
        $mode = (string) $this->settings->get('demo_mode', 'auto');
        $driver = (string) $this->settings->get('provider_driver', 'topsubre');

        if ($mode === 'on') {
            return app(DemoProvider::class);
        }

        $make = $driver === 'napxu'
            ? fn () => $this->makeNapxu()
            : fn () => $this->makeTopSubRe();

        if ($mode === 'off') {
            return $make();
        }

        return $this->hasKeyFor($driver) ? $make() : app(DemoProvider::class);
    }

    private function hasKeyFor(string $driver): bool
    {
        return $driver === 'napxu'
            ? $this->settings->hasSecret('napxu_api_key')
            : $this->settings->hasSecret('topsubre_api_key');
    }

    private function makeTopSubRe(): ProviderInterface
    {
        $key = $this->settings->getSecret('topsubre_api_key');

        if ($key === null || $key === '') {
            throw new InvalidArgumentException(
                'Chưa cấu hình API key TopSubRe. Vào Cài đặt để nhập key hoặc bật chế độ demo.'
            );
        }

        $endpoint = (string) $this->settings->get('topsubre_endpoint', 'https://likenhanh.pro/api/v2');

        return new TopSubReProvider($key, $endpoint);
    }

    private function makeNapxu(): ProviderInterface
    {
        $key = $this->settings->getSecret('napxu_api_key');

        if ($key === null || $key === '') {
            throw new InvalidArgumentException(
                'Chưa cấu hình API key napxu. Vào Cài đặt để nhập key hoặc bật chế độ demo.'
            );
        }

        return new NapxuProvider(
            $key,
            (string) $this->settings->get('napxu_base_url', 'https://napxu.vn/api'),
            $this->pricing->exchangeRate(),
        );
    }
}
