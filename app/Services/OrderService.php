<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\ApiSyncLog;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Providers\ProviderManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Đặt đơn hàng — luồng quan trọng nhất phía khách.
 *
 * Thứ tự bắt buộc (spec 5.3):
 *  1. Validate + khoá user
 *  2. INSERT order (cần id để làm khoá idempotency)
 *  3. Trừ tiền với key order:{id}:charge
 *  4. status = charged → gọi nhà cung cấp
 *  5. Thành công: processing + provider_order_id
 *     Thất bại: hoàn tiền toàn bộ + status = error
 */
class OrderService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly RefundService $refunds,
        private readonly PricingService $pricing,
        private readonly ProviderManager $providers,
    ) {
    }

    /**
     * Bước 1: xem trước — KHÔNG ghi gì vào DB.
     *
     * @return array{service: Service, total: int, balance: int, balance_after: int, quantity: int, unit_price: int}
     */
    public function preview(int $userId, int $serviceId, string $link, int $quantity): array
    {
        $service = $this->validateInput($serviceId, $link, $quantity);
        $user = User::findOrFail($userId);

        $total = $this->pricing->orderTotal($service->sell_price, $quantity);

        return [
            'service' => $service,
            'total' => $total,
            'balance' => (int) $user->balance,
            'balance_after' => (int) $user->balance - $total,
            'quantity' => $quantity,
            'unit_price' => (int) $service->sell_price,
        ];
    }

    /** Bước 2: xác nhận đặt đơn. */
    public function place(int $userId, int $serviceId, string $link, int $quantity, string $clientToken): Order
    {
        $service = $this->validateInput($serviceId, $link, $quantity);

        // Chống double-submit: nếu token đã dùng thì trả về đơn cũ ngay.
        $existing = Order::where('client_token', $clientToken)->first();
        if ($existing !== null) {
            return $existing;
        }

        $total = $this->pricing->orderTotal($service->sell_price, $quantity);
        $cost = $this->pricing->orderCost($service->cost_price, $quantity);

        try {
            $order = DB::transaction(function () use (
                $userId, $service, $link, $quantity, $clientToken, $total, $cost
            ) {
                // Khoá dòng user trước để hai request song song không cùng đọc số dư cũ.
                $user = User::query()->lockForUpdate()->findOrFail($userId);

                // (b) Tạo đơn trước — cần id cho khoá idempotency.
                $order = Order::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'client_token' => $clientToken,
                    'target_link' => $link,
                    'quantity' => $quantity,
                    'unit_price' => (int) $service->sell_price,
                    'total_amount' => $total,
                    'cost_amount' => $cost,
                    'status' => Order::STATUS_PENDING,
                ]);

                // (c) Trừ tiền — ném InsufficientBalanceException nếu thiếu.
                $this->wallet->apply(
                    userId: $user->id,
                    type: WalletTransaction::TYPE_ORDER_CHARGE,
                    amount: -$total,
                    idempotencyKey: "order:{$order->id}:charge",
                    note: "Thanh toán đơn #{$order->id}",
                    orderId: $order->id,
                );

                // (d) Đánh dấu đã trừ tiền, chưa gửi NCC.
                $order->forceFill(['status' => Order::STATUS_CHARGED])->save();

                return $order;
            });
        } catch (QueryException $e) {
            // Hai request cùng client_token lọt qua bước kiểm tra đầu → UNIQUE chặn.
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                $existing = Order::where('client_token', $clientToken)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }

        return $this->sendToProvider($order);
    }

    /** Gửi đơn lên nhà cung cấp; thất bại thì hoàn tiền toàn bộ. */
    private function sendToProvider(Order $order): Order
    {
        try {
            $result = $this->providers->driver()->addOrder(
                (string) $order->service->provider_service_id,
                $order->target_link,
                (int) $order->quantity,
                (string) $order->client_token, // reference để provider đối chiếu đúng đơn
            );

            $order->forceFill([
                'provider_order_id' => $result->providerOrderId,
                'status' => Order::STATUS_PROCESSING,
            ])->save();

            ApiSyncLog::create([
                'action' => 'add_order',
                'order_id' => $order->id,
                'request_summary' => 'service=' . $order->service->provider_service_id
                    . ', qty=' . $order->quantity,
                'response_summary' => 'order=' . $result->providerOrderId,
                'success' => true,
                'created_at' => now(),
            ]);

            return $order;
        } catch (ProviderException $e) {
            $this->markFailed($order, $e->getMessage());

            return $order->fresh();
        } catch (\Throwable $e) {
            // Lỗi bất ngờ (mạng, timeout...) cũng phải hoàn tiền.
            Log::error('Đặt đơn lỗi không xác định', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            $this->markFailed($order, 'Không gửi được đơn tới nhà cung cấp.');

            return $order->fresh();
        }
    }

    /** Đánh dấu đơn lỗi + hoàn 100% tiền. */
    private function markFailed(Order $order, string $message): void
    {
        DB::transaction(function () use ($order, $message) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $locked->forceFill([
                'status' => Order::STATUS_ERROR,
                'error_message' => mb_substr($message, 0, 500),
                // remains = quantity → RefundService hoàn toàn bộ tiền khách đã trả.
                'remains' => (int) $locked->quantity,
            ])->save();

            ApiSyncLog::create([
                'action' => 'add_order',
                'order_id' => $locked->id,
                'request_summary' => 'service=' . $locked->service_id . ', qty=' . $locked->quantity,
                'response_summary' => mb_substr($message, 0, 500),
                'success' => false,
                'created_at' => now(),
            ]);
        });

        $this->refunds->refundOrder($order->fresh(), 'provider_error');
    }

    /** Kiểm tra đầu vào dùng chung cho preview và place. */
    private function validateInput(int $serviceId, string $link, int $quantity): Service
    {
        $link = trim($link);

        // Chỉ nhận http/https — chặn javascript:, data:, file:...
        if (! preg_match('#^https?://[^\s]+$#i', $link)) {
            throw ValidationException::withMessages([
                'target_link' => 'Link không hợp lệ. Vui lòng nhập link bắt đầu bằng http:// hoặc https://.',
            ]);
        }

        if (mb_strlen($link) > 2048) {
            throw ValidationException::withMessages(['target_link' => 'Link quá dài.']);
        }

        $service = Service::where('is_active', true)->find($serviceId);

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => 'Dịch vụ không tồn tại hoặc đã ngừng bán.',
            ]);
        }

        if ($quantity < (int) $service->min_qty || $quantity > (int) $service->max_qty) {
            throw ValidationException::withMessages([
                'quantity' => "Số lượng phải từ {$service->min_qty} đến {$service->max_qty}.",
            ]);
        }

        return $service;
    }
}
