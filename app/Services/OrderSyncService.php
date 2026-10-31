<?php

namespace App\Services;

use App\Models\ApiSyncLog;
use App\Models\Order;
use App\Providers\ProviderManager;
use Illuminate\Support\Facades\Log;

/**
 * Đồng bộ trạng thái đơn từ nhà cung cấp.
 *
 * Nguyên tắc:
 *  - Chỉ xử lý đơn `processing` (đã có provider_order_id).
 *  - Đơn `charged` quá 10 phút → `stuck_review` (KHÔNG tự hoàn tiền,
 *    vì có thể đã gửi thành công mà chưa kịp lưu mã).
 *  - partial/canceled → hoàn tiền phần chưa chạy qua RefundService (đã chống trùng).
 *  - Lỗi gọi API: giữ nguyên trạng thái, ghi log, không làm sập batch.
 */
class OrderSyncService
{
    private const STUCK_MINUTES = 10;
    private const MIN_AGE_SECONDS = 60;

    public function __construct(
        private readonly ProviderManager $providers,
        private readonly RefundService $refunds,
    ) {
    }

    public function syncOrder(Order $order): void
    {
        if ($order->isFinished()) {
            return;
        }

        if ($order->status === Order::STATUS_STUCK_REVIEW) {
            return;
        }

        if (empty($order->provider_order_id)) {
            return;
        }

        $status = $this->providers->driver()->orderStatus((string) $order->provider_order_id);

        ApiSyncLog::create([
            'action' => 'check_status',
            'order_id' => $order->id,
            'request_summary' => 'order=' . $order->provider_order_id,
            'response_summary' => 'status=' . $status->status
                . ', start=' . ($status->startCount ?? '-')
                . ', remains=' . ($status->remains ?? '-'),
            'success' => true,
            'created_at' => now(),
        ]);

        // Trạng thái lạ: giữ nguyên, để admin xem log.
        if ($status->status === 'unknown') {
            return;
        }

        $order->forceFill([
            'start_count' => $status->startCount ?? $order->start_count,
            'remains' => $status->remains ?? $order->remains,
            'synced_at' => now(),
            'sync_attempts' => (int) $order->sync_attempts + 1,
        ])->save();

        $newStatus = match ($status->status) {
            'pending' => Order::STATUS_PENDING,
            'processing' => Order::STATUS_PROCESSING,
            'completed' => Order::STATUS_COMPLETED,
            'partial' => Order::STATUS_PARTIAL,
            'canceled' => Order::STATUS_CANCELED,
            default => $order->status,
        };

        $order->forceFill(['status' => $newStatus])->save();

        // Hoàn tiền phần chưa chạy — RefundService tự chống trùng nhiều lớp.
        if ($newStatus === Order::STATUS_PARTIAL) {
            $this->refunds->refundOrder($order->fresh(), 'auto_partial');
        } elseif ($newStatus === Order::STATUS_CANCELED) {
            $this->refunds->refundOrder($order->fresh(), 'auto_canceled');
        }
    }

    /**
     * @return array{synced: int, failed: int}
     */
    public function syncBatch(int $limit = 100): array
    {
        $orders = Order::query()
            ->where('status', Order::STATUS_PROCESSING)
            ->whereNotNull('provider_order_id')
            ->where('created_at', '<=', now()->subSeconds(self::MIN_AGE_SECONDS))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $this->syncOrder($order);
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Đồng bộ đơn thất bại', ['order_id' => $order->id, 'error' => $e->getMessage()]);

                ApiSyncLog::create([
                    'action' => 'check_status',
                    'order_id' => $order->id,
                    'request_summary' => 'order=' . $order->provider_order_id,
                    'response_summary' => mb_substr($e->getMessage(), 0, 500),
                    'success' => false,
                    'created_at' => now(),
                ]);
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Đơn đã trừ tiền nhưng chưa có mã nhà cung cấp sau 10 phút
     * → chuyển `stuck_review` để admin xử lý tay.
     * TUYỆT ĐỐI không tự hoàn tiền: đơn có thể đã chạy thật bên NCC.
     */
    public function flagStuckOrders(): int
    {
        return Order::query()
            ->where('status', Order::STATUS_CHARGED)
            ->whereNull('provider_order_id')
            ->where('created_at', '<', now()->subMinutes(self::STUCK_MINUTES))
            ->update(['status' => Order::STATUS_STUCK_REVIEW, 'updated_at' => now()]);
    }
}
