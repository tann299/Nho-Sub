<?php

namespace App\Providers\Demo;

use App\Exceptions\ProviderException;
use App\Models\Order;
use App\Providers\Dto\ProviderBalance;
use App\Providers\Dto\ProviderOrderResult;
use App\Providers\Dto\ProviderOrderStatus;
use App\Providers\Dto\ProviderService;
use App\Providers\ProviderInterface;
use App\Services\SettingService;

/**
 * Nhà cung cấp giả lập — dùng khi chưa cấu hình API key TopSubRe
 * hoặc khi admin bật "chế độ demo".
 *
 * Trạng thái đơn tiến hoá theo thời gian kể từ lúc tạo:
 *   0–60s      → pending
 *   60–180s    → processing
 *   > 180s     → completed (mặc định), partial hoặc canceled theo demo_scenario
 *
 * provider_order_id có dạng "demo:{orderId}" để truy ngược đơn gốc.
 */
class DemoProvider implements ProviderInterface
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function balance(): ProviderBalance
    {
        return new ProviderBalance(usd: '999.000000');
    }

    public function services(): array
    {
        $rows = [
            ['demo-1', 'Facebook — Like bài viết', 'Facebook', '0.080000', 100, 100000],
            ['demo-2', 'Facebook — Follow trang', 'Facebook', '0.150000', 500, 500000],
            ['demo-3', 'TikTok — View video', 'TikTok', '0.010000', 1000, 10000000],
            ['demo-4', 'TikTok — Like video', 'TikTok', '0.060000', 100, 1000000],
            ['demo-5', 'Instagram — Follow', 'Instagram', '0.120000', 100, 500000],
            ['demo-6', 'YouTube — Subscriber', 'YouTube', '0.800000', 100, 50000],
        ];

        return array_map(fn ($r) => new ProviderService(
            id: $r[0],
            name: $r[1],
            category: $r[2],
            description: 'Dịch vụ mô phỏng cho môi trường demo.',
            costUsd: $r[3],
            minQty: $r[4],
            maxQty: $r[5],
        ), $rows);
    }

    public function addOrder(string $serviceId, string $link, int $quantity): ProviderOrderResult
    {
        $scenario = $this->settings->get('demo_scenario', 'auto');

        // Kịch bản "fail": giả lập nhà cung cấp từ chối → dùng để test hoàn tiền.
        if ($scenario === 'fail') {
            throw new ProviderException('Nhà cung cấp tạm thời không nhận đơn (chế độ demo).');
        }

        // Lấy đơn vừa tạo gần nhất của link + số lượng này để gắn mã demo.
        $order = Order::query()
            ->where('target_link', $link)
            ->where('quantity', $quantity)
            ->latest('id')
            ->first();

        $orderId = $order?->id ?? 0;

        return new ProviderOrderResult(providerOrderId: 'demo:' . $orderId);
    }

    public function orderStatus(string $providerOrderId): ProviderOrderStatus
    {
        if (! str_starts_with($providerOrderId, 'demo:')) {
            throw new ProviderException('Mã đơn demo không hợp lệ.');
        }

        $orderId = (int) substr($providerOrderId, 5);
        $order = Order::find($orderId);

        if (! $order) {
            throw new ProviderException('Không tìm thấy đơn demo tương ứng.');
        }

        $elapsed = $order->created_at?->diffInSeconds(now()) ?? 0;
        $scenario = $this->settings->get('demo_scenario', 'auto');

        if ($elapsed < 60) {
            return new ProviderOrderStatus(status: 'pending', startCount: 0, remains: $order->quantity);
        }

        if ($elapsed < 180) {
            $done = (int) floor($order->quantity * 0.3);

            return new ProviderOrderStatus(status: 'processing', startCount: 0, remains: $order->quantity - $done);
        }

        return match ($scenario) {
            'partial' => new ProviderOrderStatus(
                status: 'partial',
                startCount: 0,
                remains: (int) floor($order->quantity * 0.4),
            ),
            'cancel' => new ProviderOrderStatus(
                status: 'canceled',
                startCount: 0,
                remains: $order->quantity,
            ),
            default => new ProviderOrderStatus(status: 'completed', startCount: 0, remains: 0),
        };
    }
}
