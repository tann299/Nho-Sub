<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_hien_day_du_cac_khu_vuc(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Like', 'platform' => 'facebook']);
        Service::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Like bài viết Facebook',
            'sell_price' => 3380,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('NhoSub')
            ->assertSee('Sub nhỏ, lớn dần cùng bạn')
            ->assertSee('Tính năng')
            ->assertSee('Bảng giá')
            ->assertSee('Đánh giá')
            ->assertSee('Câu hỏi thường gặp')
            ->assertSee('Like bài viết Facebook')
            ->assertSee('Đăng ký');
    }

    public function test_landing_khong_lo_gia_goc(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create([
            'category_id' => $cat->id, 'cost_price' => 7777, 'sell_price' => 9999, 'is_active' => true,
        ]);

        $this->get('/')->assertDontSee('7.777');
    }

    public function test_nguoi_da_dang_nhap_thay_nut_vao_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertSee('Vào bảng điều khiển')
            ->assertDontSee('Đăng ký miễn phí')
            ->assertDontSee('>Đăng ký<');
    }

    public function test_trang_404_hien_tieng_viet(): void
    {
        $this->get('/khong-ton-tai')->assertNotFound()->assertSee('Không tìm thấy trang');
    }
}
