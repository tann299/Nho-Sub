<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Hoàn tiền nhiều đợt cho một đơn.
 *
 * Vì sao nhiều đợt: đơn có thể partial (hoàn phần chưa chạy), sau đó NCC
 * báo canceled với số liệu khác → phải hoàn tiếp phần chênh lệch.
 *
 * Chống trùng 3 lớp:
 *  1. Khoá dòng đơn (lockForUpdate) → hai cron không cùng tính delta.
 *  2. seq tăng dần + UNIQUE(order_id, seq) → không ghi trùng đợt.
 *  3. idempotency_key = order:{id}:refund:{seq} → ví không áp dụng hai lần.
 */
class RefundService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function refundOrder(Order $order, string $reason): ?OrderRefund
    {
        return DB::transaction(function () use ($order, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            // Đơn hoàn thành thì không còn gì để hoàn.
            if ($locked->status === Order::STATUS_COMPLETED) {
                return null;
            }

            $unused = $locked->unusedQuantity();
            $shouldRefund = (int) floor($locked->unit_price * $unused / 1000);

            // Không bao giờ hoàn vượt tổng khách đã trả.
            $shouldRefund = min($shouldRefund, (int) $locked->total_amount);

            $delta = $shouldRefund - (int) $locked->refunded_amount;

            if ($delta <= 0) {
                return null;
            }

            $seq = (int) $locked->refund_count + 1;

            // Gọi ví với cap = phần còn được hoàn để chặn cứng ở tầng tiền.
            $tx = $this->wallet->apply(
                userId: (int) $locked->user_id,
                type: WalletTransaction::TYPE_REFUND,
                amount: $delta,
                idempotencyKey: "order:{$locked->id}:refund:{$seq}",
                note: "Hoàn tiền đơn #{$locked->id} — {$reason}",
                orderId: (int) $locked->id,
                refundCapRemaining: (int) $locked->total_amount - (int) $locked->refunded_amount,
            );

            $refund = OrderRefund::create([
                'order_id' => $locked->id,
                'seq' => $seq,
                'amount' => $delta,
                'reason' => $reason,
                'wallet_transaction_id' => $tx->id,
            ]);

            $locked->forceFill([
                'refunded_amount' => (int) $locked->refunded_amount + $delta,
                'refund_count' => $seq,
            ])->save();

            return $refund;
        });
    }
}
