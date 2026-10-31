<?php

namespace App\Providers\TopSubRe;

use App\Exceptions\ProviderException;
use App\Providers\Dto\ProviderBalance;
use App\Providers\Dto\ProviderOrderResult;
use App\Providers\Dto\ProviderOrderStatus;
use App\Providers\Dto\ProviderService;
use App\Providers\ProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nhà cung cấp TopSubRe — API V2 (POST JSON, có trường "key" + "action").
 * Tài liệu: https://likenhanh.pro/api/v2
 *
 * Bảo mật: key chỉ nằm trong body của request. Log và exception
 * chỉ ghi action + thông điệp lỗi đã lọc, không bao giờ ghi key.
 */
class TopSubReProvider implements ProviderInterface
{
    private const TIMEOUT = 20;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://likenhanh.pro/api/v2'
    ) {
    }

    public function balance(): ProviderBalance
    {
        $data = $this->call('balance');

        return new ProviderBalance(usd: (string) ($data['balance'] ?? '0'));
    }

    public function services(): array
    {
        $data = $this->call('services');

        if (! is_array($data)) {
            throw new ProviderException('Dữ liệu dịch vụ từ nhà cung cấp không hợp lệ.');
        }

        $list = [];
        foreach ($data as $row) {
            if (! is_array($row) || ! isset($row['service'])) {
                continue;
            }

            $list[] = new ProviderService(
                id: (string) $row['service'],
                name: (string) ($row['name'] ?? ''),
                category: (string) ($row['category'] ?? 'Khác'),
                description: (string) ($row['description'] ?? ''),
                costUsd: (string) ($row['rate'] ?? '0'),
                minQty: (int) ($row['min'] ?? 1),
                maxQty: (int) ($row['max'] ?? 1_000_000),
            );
        }

        return $list;
    }

    public function addOrder(string $serviceId, string $link, int $quantity): ProviderOrderResult
    {
        $data = $this->call('add', [
            'service' => $serviceId,
            'link' => $link,
            'quantity' => $quantity,
        ]);

        if (! isset($data['order'])) {
            throw new ProviderException('Nhà cung cấp không trả về mã đơn.');
        }

        return new ProviderOrderResult(providerOrderId: (string) $data['order']);
    }

    public function orderStatus(string $providerOrderId): ProviderOrderStatus
    {
        $data = $this->call('status', ['order' => $providerOrderId]);

        $raw = strtolower((string) ($data['status'] ?? ''));

        $mapped = match (true) {
            str_contains($raw, 'pending') => 'pending',
            str_contains($raw, 'progress') => 'processing',
            str_contains($raw, 'process') => 'processing',
            str_contains($raw, 'complete') => 'completed',
            str_contains($raw, 'partial') => 'partial',
            str_contains($raw, 'cancel'), str_contains($raw, 'refund') => 'canceled',
            default => 'unknown',
        };

        return new ProviderOrderStatus(
            status: $mapped,
            startCount: isset($data['start_count']) ? (int) $data['start_count'] : null,
            remains: isset($data['remains']) ? (int) $data['remains'] : null,
        );
    }

    /**
     * Gọi API và chuẩn hoá lỗi.
     * TopSubRe có thể trả {error: true/false} hoặc {error: "thông điệp"}.
     */
    private function call(string $action, array $params = []): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint, array_merge([
                    'key' => $this->apiKey,
                    'action' => $action,
                ], $params));
        } catch (ConnectionException $e) {
            Log::warning('TopSubRe: không kết nối được', ['action' => $action]);

            throw new ProviderException('Không kết nối được nhà cung cấp. Vui lòng thử lại sau.');
        }

        if ($response->failed()) {
            Log::warning('TopSubRe: HTTP lỗi', ['action' => $action, 'status' => $response->status()]);

            throw new ProviderException('Nhà cung cấp trả lỗi HTTP ' . $response->status() . '.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new ProviderException('Phản hồi nhà cung cấp không phải JSON hợp lệ.');
        }

        $error = $data['error'] ?? false;

        if ($error !== false && $error !== null && $error !== '' && $error !== 0) {
            // Chỉ lấy thông điệp lỗi; tuyệt đối không đưa key vào.
            $message = is_string($error) ? $error : 'Nhà cung cấp báo lỗi.';

            Log::warning('TopSubRe: báo lỗi nghiệp vụ', ['action' => $action, 'message' => $message]);

            throw new ProviderException($message);
        }

        return $data;
    }
}
