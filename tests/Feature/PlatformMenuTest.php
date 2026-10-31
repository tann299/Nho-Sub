<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S1: Sidebar submenu nền tảng — khách thấy nhóm dịch vụ theo platform với danh mục con.
 */
class PlatformMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_sidebar_hien_nhom_san_pham_voi_danh_muc_con(): void
    {
        $cat = Category::factory()->create(['name' => 'FB Like bài viết', 'platform' => 'facebook']);
        Service::factory()->create(['category_id' => $cat->id, 'is_active' => true]);

        $res = $this->actingAs($this->user)->get('/dashboard');

        $res->assertOk()
            ->assertSee('Sản phẩm & dịch vụ')
            ->assertSee('Facebook')
            ->assertSee('FB Like bài viết');
    }

    public function test_submenu_tieu_de_lay_tu_danh_muc_dang_hoat_dong_co_dich_vu(): void
    {
        // Danh mục ẩn hoặc không có dịch vụ → không hiện trong submenu.
        $an = Category::factory()->create(['name' => 'Danh mục ẩn', 'platform' => 'tiktok', 'is_active' => false]);
        $rong = Category::factory()->create(['name' => 'Danh mục trống', 'platform' => 'tiktok']);
        $cat = Category::factory()->create(['name' => 'TikTok View', 'platform' => 'tiktok']);
        Service::factory()->create(['category_id' => $cat->id, 'is_active' => true]);

        $res = $this->actingAs($this->user)->get('/dashboard');

        $res->assertOk()
            ->assertSee('TikTok View')
            ->assertDontSee('Danh mục ẩn')
            ->assertDontSee('Danh mục trống');
    }

    public function test_filter_theo_category_id_tren_trang_dich_vu(): void
    {
        $catA = Category::factory()->create(['name' => 'Cat A', 'platform' => 'facebook']);
        $catB = Category::factory()->create(['name' => 'Cat B', 'platform' => 'facebook']);
        Service::factory()->create(['category_id' => $catA->id, 'name' => 'Dịch vụ A', 'is_active' => true]);
        Service::factory()->create(['category_id' => $catB->id, 'name' => 'Dịch vụ B', 'is_active' => true]);

        $this->actingAs($this->user)->get('/services?category_id=' . $catA->id)
            ->assertOk()
            ->assertSee('Dịch vụ A')
            ->assertDontSee('Dịch vụ B');
    }

    /** S2: Trang điều khoản + FAQ route riêng, công khai. */
    public function test_trang_dieu_khoan_va_faq_cong_khai(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Điều khoản sử dụng');
        $this->get('/faq')->assertOk()->assertSee('Câu hỏi thường gặp');
    }

    /** S3: Bài viết — khách xem danh sách + chi tiết, bài nháp không hiện. */
    public function test_bai_viet_danh_sach_va_chi_tiet(): void
    {
        Post::factory()->create(['title' => 'Khuyến mãi tháng 11', 'slug' => 'khuyen-mai-thang-11', 'is_published' => true]);
        Post::factory()->create(['title' => 'Bài nháp', 'slug' => 'bai-nhap', 'is_published' => false]);

        $this->get('/posts')
            ->assertOk()
            ->assertSee('Khuyến mãi tháng 11')
            ->assertDontSee('Bài nháp');

        $this->get('/posts/khuyen-mai-thang-11')->assertOk()->assertSee('Khuyến mãi tháng 11');
        $this->get('/posts/bai-nhap')->assertNotFound();
    }

    /** S3: Banner thông báo mới nhất trên dashboard. */
    public function test_dashboard_hien_bai_viet_moi_nhat(): void
    {
        Post::factory()->create(['title' => 'Bảo trì hệ thống 2h', 'is_published' => true]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Bảo trì hệ thống 2h');
    }

    /** S3: Admin CRUD bài viết — tạo bài đăng được với is_published=1. */
    public function test_admin_tao_bai_viet_dang_ngay(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'Khuyến mãi cuối tuần',
            'body' => 'Chi tiết khuyến mãi…',
            'is_published' => 1,
        ])->assertRedirect();

        $post = Post::where('title', 'Khuyến mãi cuối tuần')->firstOrFail();
        $this->assertTrue($post->is_published);
        $this->assertSame('khuyen-mai-cuoi-tuan', $post->slug);
        $this->assertNotNull($post->published_at);
    }

    public function test_admin_sua_va_xoa_bai_viet(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['title' => 'Bài gốc', 'slug' => 'bai-goc']);

        $this->actingAs($admin)->post("/admin/posts/{$post->id}", [
            'title' => 'Bài đã sửa',
            'slug' => 'bai-da-sua',
            'body' => 'Nội dung mới',
            'is_published' => 1,
        ])->assertRedirect();

        $post->refresh();
        $this->assertSame('Bài đã sửa', $post->title);
        $this->assertSame('bai-da-sua', $post->slug);

        $this->actingAs($admin)->post("/admin/posts/{$post->id}/delete")->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    /** Slug trùng → tự sinh hậu số, không ném lỗi. */
    public function test_slug_trung_tu_sinh_hau_so(): void
    {
        $admin = User::factory()->admin()->create();
        Post::factory()->create(['slug' => 'tin-hot']);

        $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'Tin hot',
            'body' => 'x',
        ])->assertRedirect();

        $this->assertNotNull(Post::where('slug', 'tin-hot-2')->first());
    }
}
