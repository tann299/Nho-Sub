<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Models\PayOsTransaction;
use App\Services\DepositService;
use App\Services\PayOS\PayOSClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayOSController extends Controller
{
    public function __construct(
        private readonly PayOSClient $payos,
        private readonly DepositService $deposits,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->payos->verifyWebhook($payload)) {
            return response()->json(['success' => false, 'message' => 'Chữ ký không hợp lệ.'], 401);
        }

        $data = $payload['data'] ?? [];
        $orderCode = (int) ($data['orderCode'] ?? 0);
        $amount = (int) ($data['amount'] ?? 0);
        $reference = (string) ($data['reference'] ?? $data['paymentLinkId'] ?? '');

        $tx = PayOsTransaction::where('order_code', $orderCode)->first();

        // Không tìm thấy giao dịch nội bộ: ghi log để admin xử lý tay.
        if (! $tx) {
            $this->deposits->handleGatewayDeposit(
                gateway: 'payos',
                gatewayTxnId: 'payos-' . ($reference !== '' ? $reference : $orderCode),
                amount: $amount,
                content: (string) ($data['description'] ?? ''),
                payload: $payload,
            );

            return response()->json(['success' => true, 'message' => 'Đã ghi nhận.']);
        }

        // PayOS đã báo trước đó → trả 200, không cộng lại.
        if ($tx->status === 'paid') {
            return response()->json(['success' => true, 'message' => 'Đã xử lý trước đó.']);
        }

        $deposit = DepositRequest::find($tx->deposit_request_id);

        if (! $deposit) {
            return response()->json(['success' => true]);
        }

        DB::transaction(function () use ($tx, $deposit, $reference, $orderCode, $amount, $payload) {
            // Key idempotency theo orderCode: webhook gửi lại không cộng trùng.
            $this->deposits->handleGatewayDeposit(
                gateway: 'payos',
                gatewayTxnId: 'payos-' . $orderCode,
                amount: $amount,
                content: $deposit->transfer_code,
                payload: $payload,
            );

            $tx->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            if ($deposit->status === DepositRequest::STATUS_PENDING) {
                $deposit->forceFill([
                    'status' => DepositRequest::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ])->save();
            }
        });

        return response()->json(['success' => true]);
    }
}
