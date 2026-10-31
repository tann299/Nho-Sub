<?php

namespace App\Providers;

use App\Providers\Demo\DemoProvider;
use App\Providers\TopSubRe\TopSubReProvider;
use App\Services\SettingService;
use InvalidArgumentException;

/**
 * Chọn nhà cung cấp đang dùng.
 *  - demo_mode = 'on'     → Demo (bắt buộc, dù có key)
 *  - demo_mode = 'off'    → TopSubRe (bắt buộc)
 *  - demo_mode = 'auto'   → TopSubRe nếu có key, ngược lại Demo
 */
class ProviderManager
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function driver(): ProviderInterface
    {
        $mode = (string) $this->settings->get('demo_mode', 'auto');
        $hasKey = $this->settings->hasSecret('topsubre_api_key');

        if ($mode === 'on') {
            return app(DemoProvider::class);
        }

        if ($mode === 'off') {
            return $this->makeTopSubRe();
        }

        return $hasKey ? $this->makeTopSubRe() : app(DemoProvider::class);
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
}
