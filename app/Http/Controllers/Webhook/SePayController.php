<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SePayController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly SettingService $settings,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $expectedKey = $this->settings->getSecret('sepay_api_key');

        if ($expectedKey === null || $expectedKey === '') {
            // Chưa cấu hình thì không nhận gì cả — tránh cổng mở toang.
            return response()->json(['success' => false, 'message' => 'Cổng chưa cấu hình.'], 503);
        }

        $header = (string) $request->header('Authorization', '');
        $provided = trim((string) preg_replace('/^Apikey\s+/i', '', $header));

        // hash_equals chống timing attack.
        if ($provided === '' || ! hash_equals($expectedKey, $provided)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->all();

        $result = $this->deposits->handleGatewayDeposit(
            gateway: 'sepay',
            gatewayTxnId: (string) ($data['id'] ?? $data['referenceCode'] ?? ''),
            amount: (int) ($data['transferAmount'] ?? 0),
            content: (string) ($data['content'] ?? $data['description'] ?? ''),
            payload: $data,
            isTransferIn: strtolower((string) ($data['transferType'] ?? 'in')) === 'in',
        );

        // Luôn 200 với giao dịch đã nhận (kể cả unmatched) để SePay không retry vô hạn;
        // admin xử lý tay qua nhật ký webhook.
        return response()->json([
            'success' => in_array($result['status'], ['processed', 'duplicate'], true),
            'status' => $result['status'],
            'message' => $result['message'],
        ]);
    }
}
