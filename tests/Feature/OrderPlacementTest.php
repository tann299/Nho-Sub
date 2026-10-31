<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;
    private Service $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->set('demo_scenario', 'auto');
        app(SettingService::class)->set('demo_mode', 'on');

        $this->orders = app(OrderService::class);
        $this->service = Service::factory()->create([
            'provider_service_id' => 'demo-1',
            'sell_price' => 3380,
            'cost_price' => 2600,
            'min_qty' => 100,
            'max_qty' => 100000,
            'is_active' => true,
        ]);
        $this->user = User::factory()->create(['balance' => 100000]);
    }

    public function test_xem_truoc_tinh_dung_tong_tien(): void
    {
        $preview = $this->orders->preview($this->user->id, $this->service->id, 'https://fb.com/p/1', 1500);

        $this->assertSame(5070, $preview['total']);       // 3380 * 1500 / 1000
        $this->assertSame(100000, $preview['balance']);
        $this->assertSame(94930, $preview['balance_after']);
    }

    public function test_dat_don_tru_tien_va_chuyen_processing(): void
    {
        $order = $this->orders->place(
            $this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid()
        );

        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertSame('demo:' . $order->id, $order->provider_order_id);
        $this->assertSame(3380, $order->total_amount);
        $this->assertSame(2600, $order->cost_amount);
        $this->assertSame(3380, $order->unit_price);
        $this->assertSame(96620, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'order_charge')->count());
    }

    public function test_gia_duoc_dong_bang_tai_thoi_diem_dat(): void
    {
        $order = $this->orders->place(
            $this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid()
        );

        $this->service->forceFill(['sell_price' => 9999])->save();

        $this->assertSame(3380, $order->fresh()->unit_price);
    }

    public function test_so_du_khong_du_bi_chan_va_khong_tao_don(): void
    {
        $this->user->forceFill(['balance' => 100])->save();

        try {
            $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid());
            $this->fail('Phải ném InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            // đúng
        }

        $this->assertSame(0, Order::count(), 'Không được tạo đơn khi thiếu tiền');
        $this->assertSame(100, $this->user->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_provider_loi_thi_hoan_tien_va_danh_dau_error(): void
    {
        app(SettingService::class)->set('demo_scenario', 'fail');

        $order = $this->orders->place(
            $this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid()
        );

        $this->assertSame(Order::STATUS_ERROR, $order->status);
        $this->assertSame(3380, $order->refunded_amount);
        $this->assertSame(1, $order->refund_count);
        $this->assertSame(100000, $this->user->fresh()->balance, 'Phải hoàn lại đủ tiền');
        $this->assertNotNull($order->error_message);
    }

    public function test_cung_client_token_khong_tao_don_thu_hai(): void
    {
        $token = (string) Str::uuid();

        $first = $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, $token);
        $second = $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, $token);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, WalletTransaction::where('type', 'order_charge')->count());
        $this->assertSame(96620, $this->user->fresh()->balance, 'Chỉ trừ tiền một lần');
    }

    public function test_so_luong_ngoai_min_max_bi_tu_choi(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 50, (string) Str::uuid());
    }

    public function test_link_javascript_bi_tu_choi(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->orders->place($this->user->id, $this->service->id, 'javascript:alert(1)', 1000, (string) Str::uuid());
    }

    public function test_dich_vu_bi_an_khong_dat_duoc(): void
    {
        $this->service->forceFill(['is_active' => false])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid());
    }
}
