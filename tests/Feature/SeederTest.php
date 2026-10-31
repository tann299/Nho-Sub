<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mô phỏng server mới: không có dấu đã seed. Nếu không, file dấu
        // từ lần seed dev chặn AdminSeeder và test không bao giờ thấy admin.
        \Illuminate\Support\Facades\Storage::disk('local')->delete('.admin_seeded');
    }

    public function test_seeder_tao_admin_danh_muc_dich_vu_va_cau_hinh(): void
    {
        $this->seed();

        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->username);
        $this->assertSame(0, (int) $admin->balance);

        $this->assertGreaterThan(0, Category::count());
        $this->assertGreaterThan(0, Service::count());
        $this->assertSame('26000', Setting::where('key', 'exchange_rate')->value('value'));
        $this->assertSame('30', Setting::where('key', 'default_markup_percent')->value('value'));
    }

    public function test_seeder_admin_chi_chay_mot_lan(): void
    {
        $this->seed();
        $countTruoc = User::where('role', 'admin')->count();

        $this->seed(); // chạy lại toàn bộ

        $this->assertSame($countTruoc, User::where('role', 'admin')->count());
    }
}
