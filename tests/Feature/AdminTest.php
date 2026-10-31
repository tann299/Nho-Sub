<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingService::class)->set('demo_mode', 'on');
        $this->admin = User::factory()->admin()->create(['balance' => 0]);
    }

    public function test_tong_quan_admin_hien_chi_so(): void
    {
        $u = User::factory()->create(['balance' => 100000]);
        Order::factory()->create([
            'user_id' => $u->id,
            'total_amount' => 50000,
            'cost_amount' => 30000,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertSee('Doanh thu')
            ->assertSee('50.000');
    }

    public function test_admin_xem_danh_sach_nguoi_dung_va_tim_kiem(): void
    {
        User::factory()->create(['username' => 'khachabc']);

        $this->actingAs($this->admin)->get('/admin/users?q=khachabc')
            ->assertOk()
            ->assertSee('khachabc');
    }

    public function test_admin_cong_tien_cho_khach(): void
    {
        $u = User::factory()->create(['balance' => 0]);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => 50000,
            'reason' => 'Bù tiền sự cố',
            'client_token' => '0f14d0ab-9605-4a62-a9e4-5ed26688389b',
        ])->assertRedirect();

        $this->assertSame(50000, $u->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'adjust')->count());
    }

    /** M9: double-POST cùng client_token chỉ được áp dụng một lần. */
    public function test_admin_cong_tien_double_post_khong_cong_trung(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $token = '7b8f5c1e-3d2a-4c9b-8e7f-1a2b3c4d5e6f';

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => 50000,
            'reason' => 'Bù tiền sự cố',
            'client_token' => $token,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => 50000,
            'reason' => 'Bù tiền sự cố',
            'client_token' => $token,
        ])->assertRedirect();

        $this->assertSame(50000, $u->fresh()->balance, 'Double-POST không được cộng hai lần');
        $this->assertSame(1, WalletTransaction::where('type', 'adjust')->count());
    }

    public function test_admin_tru_qua_so_du_bi_chan(): void
    {
        $u = User::factory()->create(['balance' => 1000]);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => -5000,
            'reason' => 'Thu hồi',
        ])->assertSessionHasErrors();

        $this->assertSame(1000, $u->fresh()->balance);
    }

    public function test_cong_tien_bat_buoc_ly_do(): void
    {
        $u = User::factory()->create(['balance' => 0]);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => 10000,
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, $u->fresh()->balance);
    }

    public function test_admin_khoa_mo_tai_khoan(): void
    {
        $u = User::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/toggle")->assertRedirect();
        $this->assertSame('locked', $u->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/toggle")->assertRedirect();
        $this->assertSame('active', $u->fresh()->status);
    }

    public function test_khong_the_khoa_admin_cuoi_cung(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/toggle")
            ->assertSessionHasErrors();

        $this->assertSame('active', $this->admin->fresh()->status);
    }

    public function test_admin_xem_tat_ca_don_va_loc(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Order::factory()->create(['user_id' => $u1->id, 'status' => Order::STATUS_COMPLETED]);
        Order::factory()->create(['user_id' => $u2->id, 'status' => Order::STATUS_PROCESSING]);

        $this->actingAs($this->admin)->get('/admin/orders?status=completed')
            ->assertOk()
            ->assertSee('Hoàn thành');
    }

    public function test_admin_hoan_tien_thu_cong(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $order = Order::factory()->create([
            'user_id' => $u->id,
            'status' => Order::STATUS_PROCESSING,
            'total_amount' => 3380,
            'unit_price' => 3380,
            'quantity' => 1000,
            'remains' => 1000,
            'refunded_amount' => 0,
        ]);

        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund", [
            'reason' => 'Khách phản ánh',
        ])->assertRedirect();

        $this->assertSame(3380, $u->fresh()->balance);
        $this->assertSame(3380, $order->fresh()->refunded_amount);
    }

    public function test_admin_xu_ly_don_stuck_review(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $order = Order::factory()->create([
            'user_id' => $u->id,
            'status' => Order::STATUS_STUCK_REVIEW,
            'provider_order_id' => null,
            'total_amount' => 3380,
            'unit_price' => 3380,
            'quantity' => 1000,
            'remains' => 1000,
            'refunded_amount' => 0,
        ]);

        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/resolve-stuck", [
            'action' => 'refund',
            'reason' => 'Chưa gửi lên NCC',
        ])->assertRedirect();

        $this->assertSame(3380, $u->fresh()->balance);
        $this->assertSame(Order::STATUS_ERROR, $order->fresh()->status);
    }

    public function test_khach_thuong_khong_vao_duoc_bat_ky_trang_admin(): void
    {
        $u = User::factory()->create();

        foreach (['/admin', '/admin/users', '/admin/orders', '/admin/services', '/admin/deposits', '/admin/webhooks', '/admin/settings'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }
    }

    // ---------- Task 18: dịch vụ, duyệt nạp, webhook, cấu hình ----------

    public function test_admin_dong_bo_dich_vu(): void
    {
        $this->actingAs($this->admin)->post('/admin/services/sync')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertGreaterThan(0, \App\Models\Service::count());
    }

    public function test_admin_doi_gia_ban_dich_vu(): void
    {
        $svc = \App\Models\Service::factory()->create(['sell_price' => 3000]);

        $this->actingAs($this->admin)->post("/admin/services/{$svc->id}", [
            'sell_price' => 5000,
            'is_active' => 1,
        ])->assertRedirect();

        $svc->refresh();
        $this->assertSame(5000, $svc->sell_price);
        $this->assertTrue($svc->sell_price_override, 'Đặt giá riêng phải bật cờ override');
    }

    public function test_admin_duyet_nap_tay_cong_tien_mot_lan(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $req = app(\App\Services\DepositService::class)->createManualRequest($u->id, 50000);

        $this->actingAs($this->admin)->post("/admin/deposits/{$req->id}/approve")->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/deposits/{$req->id}/approve")->assertRedirect();

        $this->assertSame(50000, $u->fresh()->balance);
    }

    public function test_admin_tu_choi_nap_bat_buoc_ly_do(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $req = app(\App\Services\DepositService::class)->createManualRequest($u->id, 50000);

        $this->actingAs($this->admin)->post("/admin/deposits/{$req->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $req->fresh()->status);
    }

    public function test_admin_xem_nhat_ky_webhook(): void
    {
        \App\Models\WebhookLog::create([
            'gateway' => 'sepay', 'gateway_txn_id' => 'TX-1', 'amount' => 20000,
            'content' => 'NHOSUB ABC', 'status' => 'unmatched', 'reason' => 'Không tìm thấy tài khoản',
        ]);

        $this->actingAs($this->admin)->get('/admin/webhooks')
            ->assertOk()
            ->assertSee('TX-1')
            ->assertSee('Không tìm thấy tài khoản');
    }

    public function test_admin_luu_cau_hinh_ma_hoa_key(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', [
            'exchange_rate' => '26500',
            'default_markup_percent' => '35',
            'min_deposit' => '20000',
            'bank_name' => 'Vietcombank',
            'bank_account' => '0123456789',
            'bank_owner' => 'NGUYEN VAN A',
            'topsubre_api_key' => 'new-secret-key-9999',
        ])->assertRedirect();

        $s = app(SettingService::class);
        $s->forgetCache();

        $this->assertSame(26500, $s->getInt('exchange_rate'));
        $this->assertSame('35', $s->getDecimal('default_markup_percent'));
        $this->assertSame('new-secret-key-9999', $s->getSecret('topsubre_api_key'));

        // Trang cấu hình không được hiển thị key nguyên văn.
        $this->actingAs($this->admin)->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('new-secret-key-9999')
            ->assertSee('9999');
    }

    public function test_khong_nhap_key_moi_thi_key_cu_duoc_giu(): void
    {
        app(SettingService::class)->setSecret('topsubre_api_key', 'old-key-1234');

        $this->actingAs($this->admin)->post('/admin/settings', [
            'exchange_rate' => '26000',
            'default_markup_percent' => '30',
            'min_deposit' => '10000',
            'topsubre_api_key' => '',
        ])->assertRedirect();

        $s = app(SettingService::class);
        $s->forgetCache();

        $this->assertSame('old-key-1234', $s->getSecret('topsubre_api_key'));
    }

    public function test_admin_xem_nhat_ky_api(): void
    {
        \App\Models\ApiSyncLog::create([
            'action' => 'add_order', 'request_summary' => 'qty=1000',
            'response_summary' => 'ok', 'success' => true, 'created_at' => now(),
        ]);

        $this->actingAs($this->admin)->get('/admin/api-logs')->assertOk()->assertSee('add_order');
    }
}
