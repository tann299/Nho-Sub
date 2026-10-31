<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kiểm tra nền tảng: kết nối DB, timezone, chuỗi locale.
 * Đây là "canary" — nếu test này đỏ thì mọi test khác vô nghĩa.
 */
class BootstrapTest extends TestCase
{
    public function test_ket_noi_mysql_hoat_dong(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringContainsString('nhosub', DB::connection()->getDatabaseName());
    }

    public function test_timezone_la_gio_viet_nam(): void
    {
        // Người dùng Việt kỳ vọng "ngày tạo đơn" đúng giờ VN, không phải UTC.
        $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
        $this->assertSame('Asia/Ho_Chi_Minh', date_default_timezone_get());
    }

    public function test_locale_tieng_viet(): void
    {
        $this->assertSame('vi', config('app.locale'));
        $this->assertSame('vi', config('app.fallback_locale'));
    }
}
