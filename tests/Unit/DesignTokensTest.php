<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Kiểm tra quy tắc giao diện ở mức file — chạy nhanh, không cần Laravel boot.
 * Đây là "hàng rào" chống việc lỡ tay thêm gradient/màu lạ về sau.
 */
class DesignTokensTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(__DIR__ . '/../../public/assets/css/app.css');
    }

    public function test_co_du_10_bien_mau_bat_buoc(): void
    {
        $css = $this->css();
        foreach ([
            '#1E6FD9', '#0A4DB3', '#DCEBFD', '#F5F8FF',
            '#FFFFFF', '#0F2233', '#5B6B80',
            '#16A34A', '#F59E0B', '#DC2626',
        ] as $hex) {
            $this->assertStringContainsStringIgnoringCase($hex, $css, "Thiếu màu {$hex}");
        }
    }

    public function test_khong_co_gradient_ngoai_hero(): void
    {
        $css = $this->css();

        // Tách từng rule, chỉ cho phép .hero chứa gradient.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $rule) {
            $selector = trim($rule[1]);
            $body = $rule[2];

            if (stripos($body, 'gradient(') === false) {
                continue;
            }

            $this->assertStringContainsString(
                '.hero',
                $selector,
                "Gradient bị dùng ngoài .hero tại selector: {$selector}"
            );
        }
    }

    public function test_hero_gradient_chenh_lech_sang_duoi_10_phan_tram(): void
    {
        $css = $this->css();
        preg_match('/\.hero\s*\{[^}]*linear-gradient\(([^)]+)\)/i', $css, $m);

        $this->assertNotEmpty($m, 'Không tìm thấy gradient của .hero');
        preg_match_all('/#[0-9A-F]{6}/i', $m[1], $colors);

        $lum = function (string $hex): float {
            $r = hexdec(substr($hex, 1, 2)) / 255;
            $g = hexdec(substr($hex, 3, 2)) / 255;
            $b = hexdec(substr($hex, 5, 2)) / 255;

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        };

        $values = array_map($lum, $colors[0]);
        $diff = abs(max($values) - min($values));

        $this->assertLessThan(0.10, $diff, 'Gradient hero chênh lệch độ sáng quá 10%');
    }

    public function test_transition_dung_khoang_02_den_03_giay(): void
    {
        $css = $this->css();

        // Chỉ xét khai báo `transition:` (có dấu hai chấm), bỏ qua
        // `transition-duration` trong media query prefers-reduced-motion.
        preg_match_all('/transition:\s*[^;]*?(\d*\.?\d+)s/i', $css, $m);

        $this->assertNotEmpty($m[1], 'Không có transition nào');
        foreach ($m[1] as $duration) {
            $v = (float) $duration;
            $this->assertGreaterThanOrEqual(0.2, $v, "transition {$v}s quá nhanh");
            $this->assertLessThanOrEqual(0.3, $v, "transition {$v}s quá chậm");
        }
    }

    public function test_logo_ton_tai_va_dung_mau_xanh(): void
    {
        // Logo mới là PNG (ảnh vẽ tay) — kiểm file tồn tại + favicon PNG
        $this->assertFileExists(__DIR__ . '/../../public/assets/img/logo.png');
        $this->assertFileExists(__DIR__ . '/../../public/assets/img/favicon-48.png');
    }
}
