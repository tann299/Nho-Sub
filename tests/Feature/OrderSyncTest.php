<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderSyncService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private OrderSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingService::class)->set('demo_mode', 'on');
        app(SettingService::class)->set('demo_scenario', 'auto');
        $this->sync = app(OrderSyncService::class);
    }

    private function makeOrder(array $overrides = []): Order
    {
        $user = User::factory()->create(['balance' => 0]);
        // provider_service_id UNIQUE → lấy service demo-1 có sẵn hoặc tạo mới.
        $service = Service::firstOrCreate(
            ['provider_service_id' => 'demo-1'],
            Service::factory()->make(['provider_service_id' => 'demo-1'])->toArray()
        );

        return Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'quantity' => 1000,
            'unit_price' => 3380,
            'total_amount' => 3380,
            'cost_amount' => 2600,
            'status' => Order::STATUS_PROCESSING,
            'provider_order_id' => 'demo:1',
        ], $overrides));
    }

    public function test_dong_bo_don_completed(): void
    {
        $order = $this->makeOrder();
        $order->forceFill([
            'provider_order_id' => 'demo:' . $order->id,
            'created_at' => now()->subMinutes(5),
        ])->saveQuietly();

        $this->sync->syncOrder($order->fresh());

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(0, OrderRefund::count(), 'Đơn hoàn thành không được hoàn tiền');
    }

    public function test_partial_hoan_dung_phan_chua_chay(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');

        $order = $this->makeOrder();
        $order->forceFill([
            'provider_order_id' => 'demo:' . $order->id,
            'created_at' => now()->subMinutes(5),
        ])->saveQuietly();

        $this->sync->syncOrder($order->fresh());

        $order->refresh();
        $this->assertSame(Order::STATUS_PARTIAL, $order->status);
        // remains 400/1000 → hoàn 3380*400/1000 = 1352
        $this->assertSame(1352, $order->refunded_amount);
        $this->assertSame(1352, $order->user->fresh()->balance);
    }

    public function test_dong_bo_lai_sau_partial_khong_hoan_them(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');

        $order = $this->makeOrder();
        $order->forceFill([
            'provider_order_id' => 'demo:' . $order->id,
            'created_at' => now()->subMinutes(5),
        ])->saveQuietly();

        $this->sync->syncOrder($order->fresh());
        $this->sync->syncOrder($order->fresh());
        $this->sync->syncOrder($order->fresh());

        $this->assertSame(1352, $order->fresh()->refunded_amount);
        $this->assertSame(1, OrderRefund::count());
    }

    public function test_canceled_hoan_toan_bo(): void
    {
        app(SettingService::class)->set('demo_scenario', 'cancel');

        $order = $this->makeOrder();
        $order->forceFill([
            'provider_order_id' => 'demo:' . $order->id,
            'created_at' => now()->subMinutes(5),
        ])->saveQuietly();

        $this->sync->syncOrder($order->fresh());

        $this->assertSame(Order::STATUS_CANCELED, $order->fresh()->status);
        $this->assertSame(3380, $order->fresh()->refunded_amount);
        $this->assertSame(3380, $order->user->fresh()->balance);
    }

    public function test_don_da_ket_thuc_khong_dong_bo_lai(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_COMPLETED]);
        $truoc = $order->synced_at;

        $this->sync->syncOrder($order->fresh());

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame($truoc?->toDateTimeString(), $order->fresh()->synced_at?->toDateTimeString());
    }

    public function test_don_stuck_review_khong_bi_cron_cham(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_STUCK_REVIEW]);

        $this->sync->syncBatch();

        $this->assertSame(Order::STATUS_STUCK_REVIEW, $order->fresh()->status);
    }

    public function test_don_charged_qua_10_phut_thanh_stuck_review_khong_hoan_tien(): void
    {
        $order = $this->makeOrder([
            'status' => Order::STATUS_CHARGED,
            'provider_order_id' => null,
        ]);
        $order->forceFill(['created_at' => now()->subMinutes(15)])->saveQuietly();

        $user = $order->user;
        $user->forceFill(['balance' => 0])->save();

        $count = $this->sync->flagStuckOrders();

        $this->assertSame(1, $count);
        $this->assertSame(Order::STATUS_STUCK_REVIEW, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->balance, 'Không được tự hoàn tiền');
    }

    public function test_don_charged_moi_tao_chua_bi_danh_dau(): void
    {
        $order = $this->makeOrder([
            'status' => Order::STATUS_CHARGED,
            'provider_order_id' => null,
        ]);
        $order->forceFill(['created_at' => now()->subMinutes(2)])->saveQuietly();

        $this->assertSame(0, $this->sync->flagStuckOrders());
        $this->assertSame(Order::STATUS_CHARGED, $order->fresh()->status);
    }

    public function test_sync_batch_chi_lay_don_processing(): void
    {
        // Đơn phải cũ hơn 60 giây mới được cron chạm (tránh đụng đơn vừa đặt).
        $a = $this->makeOrder(['status' => Order::STATUS_PROCESSING]);
        $b = $this->makeOrder(['status' => Order::STATUS_COMPLETED]);
        $c = $this->makeOrder(['status' => Order::STATUS_CANCELED]);

        // Mã demo phải trỏ đúng đơn của chính nó (DemoProvider tra Order::find).
        $a->forceFill([
            'provider_order_id' => 'demo:' . $a->id,
            'created_at' => now()->subMinutes(5),
        ])->saveQuietly();

        foreach ([$b, $c] as $o) {
            $o->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();
        }

        $result = $this->sync->syncBatch();

        $this->assertSame(1, $result['synced']);
    }

    public function test_loi_provider_khong_lam_sap_cron(): void
    {
        // demo:99999 — đơn demo không tồn tại → provider ném exception → failed++.
        $order = $this->makeOrder(['provider_order_id' => 'demo:99999']);
        $order->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

        $result = $this->sync->syncBatch();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status, 'Giữ nguyên trạng thái khi lỗi');
    }
}
