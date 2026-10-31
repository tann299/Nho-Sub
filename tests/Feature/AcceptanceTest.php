<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DepositRequest;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use App\Services\OrderSyncService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nghiệm thu 6 mục theo yêu cầu dự án — chạy end-to-end như người dùng thật.
 */
class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $sepayKey = 'acceptance-key';

    protected function setUp(): void
    {
        parent::setUp();

        $s = app(SettingService::class);
        $s->setSecret('sepay_api_key', $this->sepayKey);
        $s->set('min_deposit', '10000');
        $s->set('exchange_rate', '26000');
        $s->set('default_markup_percent', '30');
        $s->set('demo_mode', 'on');
        $s->set('demo_scenario', 'auto');
    }

    /** MỤC 1: Đăng ký → đăng nhập → nạp tiền SePay → số dư tăng đúng */
    public function test_muc1_dang_ky_dang_nhap_nap_tien(): void
    {
        // Đăng ký
        $this->post('/register', [
            'username' => 'nghiemthu01',
            'email' => 'nt01@example.com',
            'password' => 'MatKhau@123',
            'password_confirmation' => 'MatKhau@123',
            'full_name' => 'Người Nghiệm Thu',
        ])->assertRedirect('/dashboard');

        $user = User::where('username', 'nghiemthu01')->firstOrFail();
        $this->assertSame(0, $user->balance);

        // Đăng xuất rồi đăng nhập lại
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => 'nghiemthu01', 'password' => 'MatKhau@123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Nạp tiền qua webhook SePay (test mode)
        $this->withHeaders(['Authorization' => 'Apikey ' . $this->sepayKey])
            ->postJson('/webhook/sepay', [
                'id' => 555001,
                'transferType' => 'in',
                'transferAmount' => 100000,
                'content' => 'NHOSUB NGHIEMTHU01 nap tien',
                'referenceCode' => 'FT555001',
            ])->assertOk();

        $this->assertSame(100000, $user->fresh()->balance, 'Số dư phải tăng đúng 100.000 ₫');
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)->count());
    }

    /** MỤC 2: Đặt đơn → trừ tiền → gửi provider → cron đồng bộ */
    public function test_muc2_dat_don_tru_tien_va_dong_bo(): void
    {
        $user = User::factory()->create(['balance' => 100000]);
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => 'demo-1',
            'sell_price' => 3380,
            'cost_price' => 2600,
            'min_qty' => 100,
            'max_qty' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post('/orders', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/nghiemthu',
            'quantity' => 1000,
            'client_token' => (string) Str::uuid(),
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(96620, $user->fresh()->balance, 'Phải trừ đúng 3.380 ₫');
        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertNotNull($order->provider_order_id);

        // Giả lập đã qua 5 phút rồi chạy cron
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        app(OrderSyncService::class)->syncBatch();

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
    }

    /** MỤC 3: Đơn partial → hoàn đúng phần chưa chạy, không hoàn trùng */
    public function test_muc3_partial_hoan_dung_va_khong_trung(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');

        $user = User::factory()->create(['balance' => 100000]);
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => 'demo-1',
            'sell_price' => 3380,
            'cost_price' => 2600,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $this->actingAs($user)->post('/orders', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/partial',
            'quantity' => 1000,
            'client_token' => (string) Str::uuid(),
        ]);

        $order = Order::firstOrFail();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();

        // Chạy cron 3 lần liên tiếp
        $sync = app(OrderSyncService::class);
        $sync->syncBatch();
        $sync->syncBatch();
        $sync->syncBatch();

        $order->refresh();

        $this->assertSame(Order::STATUS_PARTIAL, $order->status);
        // remains 400 → hoàn 3380*400/1000 = 1352
        $this->assertSame(1352, $order->refunded_amount);
        $this->assertSame(1, $order->refund_count, 'Chỉ được hoàn 1 đợt');
        $this->assertSame(1, OrderRefund::count());
        $this->assertSame(96620 + 1352, $user->fresh()->balance);

        // Số dư = số dư ban đầu + tổng sổ cái (không có giao dịch nạp trong kịch bản này)
        $tong = (int) WalletTransaction::where('user_id', $user->id)->sum('amount');
        $this->assertSame(100000 + $tong, $user->fresh()->balance);
    }

    /** MỤC 4: Webhook gửi lại trùng → không cộng trùng */
    public function test_muc4_webhook_trung_khong_cong_trung(): void
    {
        $user = User::factory()->create(['username' => 'trunglap', 'balance' => 0]);

        $payload = [
            'id' => 777001,
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'NHOSUB TRUNGLAP',
            'referenceCode' => 'FT777001',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['Authorization' => 'Apikey ' . $this->sepayKey])
                ->postJson('/webhook/sepay', $payload)->assertOk();
        }

        $this->assertSame(50000, $user->fresh()->balance, 'Gửi 5 lần vẫn chỉ cộng 1 lần');
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame(1, WebhookLog::count());
        $this->assertSame(1, DepositRequest::where('user_id', $user->id)->count());
    }

    /** MỤC 5: Admin đồng bộ dịch vụ, đổi giá, duyệt nạp tay, cộng/trừ tiền, xem webhook */
    public function test_muc5_admin_van_hanh_day_du(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. Đồng bộ dịch vụ (demo provider)
        $this->actingAs($admin)->post('/admin/services/sync')->assertRedirect();
        $this->assertGreaterThan(0, Service::count());

        // 2. Đổi giá bán
        $svc = Service::first();
        $this->actingAs($admin)->post("/admin/services/{$svc->id}", [
            'sell_price' => 9999, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(9999, $svc->fresh()->sell_price);
        $this->assertTrue($svc->fresh()->sell_price_override);

        // 3. Duyệt nạp tay
        $khach = User::factory()->create(['balance' => 0]);
        $req = app(\App\Services\DepositService::class)->createManualRequest($khach->id, 30000);
        $this->actingAs($admin)->post("/admin/deposits/{$req->id}/approve")->assertRedirect();
        $this->assertSame(30000, $khach->fresh()->balance);

        // 4. Cộng/trừ tiền (mỗi lần một token riêng — chống double-submit)
        $this->actingAs($admin)->post("/admin/users/{$khach->id}/adjust", [
            'amount' => 20000, 'reason' => 'Thưởng nghiệm thu',
            'client_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(50000, $khach->fresh()->balance);

        $this->actingAs($admin)->post("/admin/users/{$khach->id}/adjust", [
            'amount' => -10000, 'reason' => 'Trừ nghiệm thu',
            'client_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(40000, $khach->fresh()->balance);

        // 5. Xem nhật ký webhook
        $this->actingAs($admin)->get('/admin/webhooks')->assertOk();
        $this->actingAs($admin)->get('/admin/api-logs')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
    }

    /** MỤC 6: Giao diện — không gradient ngoài hero, đủ token màu, render được */
    public function test_muc6_giao_dien_dung_quy_tac(): void
    {
        // Landing + panel render không lỗi
        $this->get('/')->assertOk()->assertSee('NhoSub');

        $user = User::factory()->create(['balance' => 123456]);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('123.456');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();

        // Không gradient ngoài .hero
        $css = file_get_contents(public_path('assets/css/app.css'));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as $rule) {
            if (stripos($rule[2], 'gradient(') !== false) {
                $this->assertStringContainsString('.hero', $rule[1],
                    'Gradient ngoài .hero tại: ' . trim($rule[1]));
            }
        }

        // Không màu ngoài bảng quy định (kiểm tra các mã hex trong CSS)
        $allowed = [
            '#1E6FD9', '#0A4DB3', '#DCEBFD', '#F5F8FF', '#FFFFFF', '#0F2233',
            '#5B6B80', '#16A34A', '#F59E0B', '#DC2626',
            // Biến thể hợp lệ của trạng thái đơn (badge nền/chữ/viền)
            '#E7F7EE', '#0E7A38', '#BFEACD', '#FFF7E6', '#92580A', '#FCE3B3',
            '#FDECEC', '#A31818', '#F7C9C9', '#C6DDFB', '#EEF2F8', '#DDE5F1',
            '#EDF2FA', '#E3ECFB', '#D6E2F2', '#9AA9BC', '#B91C1C', '#EDF3FE',
        ];

        preg_match_all('/#[0-9A-Fa-f]{6}/', $css, $hexes);

        foreach (array_unique($hexes[0]) as $hex) {
            $this->assertContains(
                strtoupper($hex),
                array_map('strtoupper', $allowed),
                "Màu {$hex} không nằm trong bảng màu quy định"
            );
        }
    }
}
