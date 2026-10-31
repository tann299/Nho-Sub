<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $refunds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refunds = app(RefundService::class);
    }

    /** Đơn 1000 lượt, giá 3380đ/1000, khách đã trả 3380đ. */
    private function makeOrder(int $balance = 0, array $overrides = []): Order
    {
        $user = User::factory()->create(['balance' => $balance]);

        return Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'quantity' => 1000,
            'unit_price' => 3380,
            'total_amount' => 3380,
            'cost_amount' => 2600,
            'status' => Order::STATUS_PROCESSING,
            'refunded_amount' => 0,
            'refund_count' => 0,
        ], $overrides));
    }

    public function test_partial_hoan_phan_chua_chay(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_PARTIAL,
            'start_count' => 100,
            'remains' => 600,   // đã chạy 400
        ]);

        $refund = $this->refunds->refundOrder($order, 'auto_partial');

        $this->assertNotNull($refund);
        $this->assertSame(1, $refund->seq);
        // 3380 * 600 / 1000 = 2028
        $this->assertSame(2028, $refund->amount);
        $this->assertSame(2028, $order->fresh()->refunded_amount);
        $this->assertSame(1, $order->fresh()->refund_count);
        $this->assertSame(2028, $order->user->fresh()->balance);
    }

    public function test_cron_chay_lai_khong_hoan_them(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_PARTIAL,
            'remains' => 600,
        ]);

        $this->refunds->refundOrder($order, 'auto_partial');
        $lan2 = $this->refunds->refundOrder($order->fresh(), 'auto_partial');

        $this->assertNull($lan2);
        $this->assertSame(2028, $order->fresh()->refunded_amount);
        $this->assertSame(1, OrderRefund::count());
    }

    public function test_partial_roi_canceled_hoan_them_dot_hai(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_PARTIAL,
            'remains' => 600,
        ]);

        $dot1 = $this->refunds->refundOrder($order, 'auto_partial');
        $this->assertSame(2028, $dot1->amount);

        // NCC cập nhật: chỉ chạy được 200, còn lại 800.
        $order->refresh()->forceFill([
            'status' => Order::STATUS_CANCELED,
            'remains' => 800,
        ])->save();

        $dot2 = $this->refunds->refundOrder($order->fresh(), 'auto_canceled');

        $this->assertNotNull($dot2);
        $this->assertSame(2, $dot2->seq);
        // Đáng hoàn 3380*800/1000 = 2704; đã hoàn 2028 → hoàn thêm 676
        $this->assertSame(676, $dot2->amount);
        $this->assertSame(2704, $order->fresh()->refunded_amount);
        $this->assertSame(2, $order->fresh()->refund_count);
        $this->assertSame(2704, $order->user->fresh()->balance);
    }

    public function test_khong_hoan_vuot_tong_don(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_CANCELED,
            'remains' => 999999,
        ]);

        $refund = $this->refunds->refundOrder($order, 'auto_canceled');

        $this->assertSame(3380, $refund->amount);
        $this->assertSame(3380, $order->fresh()->refunded_amount);
    }

    public function test_don_completed_khong_hoan(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_COMPLETED,
            'start_count' => 100,
            'remains' => 0,
        ]);

        $this->assertNull($this->refunds->refundOrder($order, 'auto_partial'));
        $this->assertSame(0, OrderRefund::count());
    }

    public function test_hoan_toan_bo_khi_provider_error(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_ERROR,
            'remains' => 1000,
        ]);

        $refund = $this->refunds->refundOrder($order, 'provider_error');

        $this->assertSame(3380, $refund->amount);
        $this->assertSame('provider_error', $refund->reason);
    }

    public function test_goi_lai_voi_cung_trang_thai_khong_tao_dong_thua(): void
    {
        $order = $this->makeOrder(overrides: ['status' => Order::STATUS_PARTIAL, 'remains' => 600]);

        $a = $this->refunds->refundOrder($order, 'auto_partial');
        $b = $this->refunds->refundOrder($order->fresh(), 'auto_partial');
        $c = $this->refunds->refundOrder($order->fresh(), 'auto_partial');

        $this->assertNotNull($a);
        $this->assertNull($b);
        $this->assertNull($c);
        $this->assertSame(1, OrderRefund::count());
        $this->assertSame(2028, $order->fresh()->refunded_amount);
    }
}
