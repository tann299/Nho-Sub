<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->set('demo_mode', 'on');
        $this->user = User::factory()->create(['balance' => 50000]);
    }

    public function test_trang_tong_quan_hien_so_du_va_thong_ke(): void
    {
        Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PROCESSING,
        ]);
        Order::factory()->create(['user_id' => $this->user->id, 'status' => Order::STATUS_COMPLETED]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertSee('50.000')
            ->assertSee('4');
    }

    public function test_bang_dich_vu_khong_lo_gia_goc(): void
    {
        $cat = Category::factory()->create(['name' => 'FB Like', 'platform' => 'facebook']);
        Service::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Like bài viết',
            'cost_price' => 1234,
            'sell_price' => 3380,
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->user)->get('/services');

        $res->assertOk()
            ->assertSee('Like bài viết')
            ->assertSee('3.380')
            ->assertDontSee('1.234')
            ->assertDontSee('cost_price');
    }

    public function test_dich_vu_an_khong_hien(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'name' => 'Dịch vụ ẩn', 'is_active' => false]);

        $this->actingAs($this->user)->get('/services')->assertDontSee('Dịch vụ ẩn');
    }

    public function test_tim_kiem_dich_vu_theo_ten(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'name' => 'TikTok View', 'is_active' => true]);
        Service::factory()->create(['category_id' => $cat->id, 'name' => 'Facebook Like', 'is_active' => true]);

        $this->actingAs($this->user)->get('/services?q=tiktok')
            ->assertSee('TikTok View')
            ->assertDontSee('Facebook Like');
    }

    public function test_form_dat_don_hien_va_co_client_token(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'is_active' => true]);

        $res = $this->actingAs($this->user)->get('/orders/create');

        $res->assertOk()->assertSee('client_token');
    }

    public function test_xem_truoc_tinh_dung_tien(): void
    {
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id, 'sell_price' => 3380,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $this->actingAs($this->user)->post('/orders/preview', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/1',
            'quantity' => 1000,
        ])->assertOk()->assertSee('3.380')->assertSee('46.620');
    }

    public function test_dat_don_qua_http_tao_don_va_tru_tien(): void
    {
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id, 'sell_price' => 3380, 'cost_price' => 2600,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $token = (string) \Illuminate\Support\Str::uuid();

        $res = $this->actingAs($this->user)->post('/orders', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/1',
            'quantity' => 1000,
            'client_token' => $token,
        ]);

        $order = Order::first();
        $res->assertRedirect(route('panel.orders.show', $order));
        $this->assertSame(46620, $this->user->fresh()->balance);
        $this->assertSame($token, $order->client_token);
    }

    public function test_khach_khong_xem_duoc_don_nguoi_khac(): void
    {
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get('/orders/' . $order->id)->assertNotFound();
    }

    public function test_trang_don_hang_loc_theo_trang_thai(): void
    {
        Order::factory()->create(['user_id' => $this->user->id, 'status' => Order::STATUS_COMPLETED]);
        Order::factory()->create(['user_id' => $this->user->id, 'status' => Order::STATUS_PROCESSING]);

        $this->actingAs($this->user)->get('/orders?status=completed')
            ->assertOk()
            ->assertSee('Hoàn thành');
    }

    public function test_trang_chi_tiet_don_hien_ma_va_trang_thai(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PROCESSING,
            'start_count' => 100,
            'remains' => 900,
        ]);

        $this->actingAs($this->user)->get('/orders/' . $order->id)
            ->assertOk()
            ->assertSee('#' . $order->id)
            ->assertSee('Đang chạy');
    }

    public function test_trang_nap_tien_hien_noi_dung_chuyen_khoan_dung_format(): void
    {
        $this->actingAs($this->user)->get('/deposit')
            ->assertOk()
            ->assertSee('NHOSUB ' . strtoupper($this->user->username));
    }

    public function test_gui_yeu_cau_nap_tay_tao_pending(): void
    {
        $this->actingAs($this->user)->post('/deposit', ['amount' => 50000])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\DepositRequest::where('status', 'pending')->count());
    }

    public function test_trang_vi_hien_lich_su_giao_dich(): void
    {
        app(\App\Services\WalletService::class)->apply(
            $this->user->id, 'deposit', 20000, 'deposit:test:view', 'Nạp thử'
        );

        $this->actingAs($this->user)->get('/wallet')
            ->assertOk()
            ->assertSee('20.000')
            ->assertSee('Nạp thử');
    }

    public function test_cap_nhat_ho_so_khong_doi_duoc_username(): void
    {
        $this->actingAs($this->user)->post('/profile', [
            'full_name' => 'Tên Mới',
            'phone' => '0909999999',
            'email' => $this->user->email,
            'username' => 'hacker',
            'role' => 'admin',
        ])->assertRedirect();

        $u = $this->user->fresh();
        $this->assertSame('Tên Mới', $u->full_name);
        $this->assertSame($this->user->username, $u->username, 'Không được đổi username');
        $this->assertSame('customer', $u->role, 'Không được tự nâng quyền');
    }

    public function test_trang_ho_tro_va_thong_bao_hoat_dong(): void
    {
        $this->actingAs($this->user)->get('/support')->assertOk();
        $this->actingAs($this->user)->get('/notifications')->assertOk();
    }
}
