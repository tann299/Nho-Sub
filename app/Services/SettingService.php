<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Đọc/ghi cấu hình hệ thống.
 * - Giá trị thường: lưu thẳng.
 * - Giá trị nhạy cảm (API key): mã hoá bằng APP_KEY, không bao giờ trả về nguyên văn
 *   qua giao diện — chỉ hiển thị 4 ký tự cuối.
 * - Cache 5 phút để trang admin không query liên tục; set() sẽ xoá cache.
 */
class SettingService
{
    private const CACHE_KEY = 'nhosub.settings.all';
    private const CACHE_TTL = 300;

    /** Các key được coi là bí mật — dùng setSecret/getSecret. */
    public const SECRET_KEYS = [
        'topsubre_api_key',
        'napxu_api_key',
        'sepay_api_key',
        'payos_client_id',
        'payos_api_key',
        'payos_checksum_key',
        'momo_access_token',
        'momo_secret_key',
    ];

    private function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    /** Giá trị thập phân dạng chuỗi — tránh sai số float khi tính tiền. */
    public function getDecimal(string $key, string $default = '0'): string
    {
        $value = $this->get($key);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'updated_at' => now()]
        );

        $this->forgetCache();
    }

    /** Lưu giá trị bí mật dạng đã mã hoá. */
    public function setSecret(string $key, string $plainValue): void
    {
        if ($plainValue === '') {
            return; // Không cho phép vô tình xoá key bằng ô trống
        }

        $this->set($key, Crypt::encryptString($plainValue));
    }

    public function getSecret(string $key): ?string
    {
        $raw = $this->get($key);

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            // Key đã đổi APP_KEY → không giải mã được. Ghi log không chứa giá trị.
            logger()->warning('Không giải mã được cấu hình bí mật', ['key' => $key]);

            return null;
        }
    }

    public function hasSecret(string $key): bool
    {
        $raw = $this->get($key);

        return $raw !== null && $raw !== '';
    }

    /** Hiển thị an toàn: chỉ 4 ký tự cuối. */
    public function maskedSecret(string $key): ?string
    {
        $plain = $this->getSecret($key);

        if ($plain === null) {
            return null;
        }

        return '••••••••' . mb_substr($plain, -4);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
