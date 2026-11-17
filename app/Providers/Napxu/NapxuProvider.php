<?php

namespace App\Providers\Napxu;

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
 * Nhà cung cấp napxu.vn — REST API riêng (Bearer token, JSON).
 * Tài liệu: https://napxu.vn/api-docs (đăng nhập để xem)
 *
 * Khác TopSubRe:
 *  - Auth qua header Authorization: Bearer (không phải key trong body).
 *  - Giá gốc tính VND/đơn vị (pricePerUnit), quy về USD/1000 lượt bằng tỉ giá.
 *  - Không có endpoint status đơn lẻ — tra qua danh sách đơn (tối đa 100/trang).
 *
 * Bảo mật: key chỉ nằm trong header; log và exception không bao giờ ghi key.
 */
class NapxuProvider implements ProviderInterface
{
    private const TIMEOUT = 20;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://napxu.vn/api',
        private readonly int $exchangeRate = 26000,
    ) {
    }

    public function balance(): ProviderBalance
    {
        $data = $this->call('GET', '/auth/balance');

        return new ProviderBalance(usd: $this->vndToUsd((float) ($data['balance'] ?? 0)));
    }

    public function services(): array
    {
        $data = $this->call('GET', '/boost/service-types');

        $list = [];

        foreach ($data['platforms'] ?? [] as $platform) {
            $platformName = (string) ($platform['nameVi'] ?? 'Khác');

            foreach ($platform['serviceTypes'] ?? [] as $type) {
                $typeName = (string) ($type['nameVi'] ?? '');

                foreach ($type['servers'] ?? [] as $server) {
                    if (! isset($server['id'])) {
                        continue;
                    }

                    $list[] = new ProviderService(
                        id: 'napxu-' . $server['id'],
                        name: mb_substr($platformName . ' — ' . $typeName . ' — ' . (string) ($server['label'] ?? ''), 0, 255),
                        category: mb_substr($platformName . ' — ' . $typeName, 0, 160),
                        description: (string) ($server['descriptionVi'] ?? ''),
                        // Giá NCC là VND/đơn vị → nhân 1000 ra VND/1000 lượt rồi đổi USD.
                        costUsd: $this->vndToUsd(((int) ($server['pricePerUnit'] ?? 0)) * 1000),
                        minQty: max(1, (int) ($server['minQuantity'] ?? 1)),
                        maxQty: max(1, (int) ($server['maxQuantity'] ?? 1_000_000)),
                    );
                }
            }
        }

        return $list;
    }

    public function addOrder(string $serviceId, string $link, int $quantity, ?string $reference = null): ProviderOrderResult
    {
        // $reference không dùng với napxu — API không có trường đối chiếu.
        unset($reference);

        $serverId = (int) str_replace('napxu-', '', $serviceId);

        if ($serverId <= 0) {
            throw new ProviderException('Mã dịch vụ napxu không hợp lệ.');
        }

        $data = $this->call('POST', '/boost/orders/create', [
            'serverId' => $serverId,
            'link' => $link,
            'quantity' => $quantity,
        ]);

        $ref = (string) ($data['order']['orderRef'] ?? '');

        if ($ref === '') {
            throw new ProviderException('Nhà cung cấp không trả về mã đơn.');
        }

        return new ProviderOrderResult(providerOrderId: $ref);
    }

    public function orderStatus(string $providerOrderId): ProviderOrderStatus
    {
        // API không có tra đơn lẻ — lấy trang đầu (100 đơn mới nhất) rồi tìm theo orderRef.
        $data = $this->call('GET', '/boost/orders', query: ['page' => 1, 'limit' => 100]);

        foreach ($data['orders'] ?? [] as $row) {
            if (! is_array($row) || (string) ($row['orderRef'] ?? '') !== $providerOrderId) {
                continue;
            }

            return new ProviderOrderStatus(
                status: $this->mapStatus((string) ($row['status'] ?? '')),
                startCount: isset($row['startCount']) && $row['startCount'] !== null && $row['startCount'] !== ''
                    ? (int) $row['startCount'] : null,
                remains: isset($row['remains']) && $row['remains'] !== null && $row['remains'] !== ''
                    ? (int) $row['remains'] : null,
            );
        }

        // Đơn không thấy trong 100 đơn mới nhất (đơn cũ hoặc đã bị xoá) — giữ trạng thái chờ admin xem log.
        return new ProviderOrderStatus(status: 'unknown', startCount: null, remains: null);
    }

    /** Map trạng thái napxu về 5 trạng thái chung của hệ thống. */
    private function mapStatus(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return match (true) {
            $raw === 'pending' => 'pending',
            $raw === 'processing', $raw === 'in_progress', $raw === 'in progress' => 'processing',
            str_contains($raw, 'progress'), str_contains($raw, 'process') => 'processing',
            $raw === 'success', $raw === 'completed' => 'completed',
            str_contains($raw, 'complete') => 'completed',
            str_contains($raw, 'partial') => 'partial',
            $raw === 'refund', $raw === 'canceled', $raw === 'cancelled', $raw === 'failed' => 'canceled',
            str_contains($raw, 'cancel'), str_contains($raw, 'refund') => 'canceled',
            default => 'unknown',
        };
    }

    /**
     * VND → USD, làm tròn LÊN 6 chữ số thập phân.
     * Cost không được thấp hơn giá gốc — làm tròn xuống sẽ bán lỗ phần lẻ.
     */
    private function vndToUsd(float|int $vnd): string
    {
        $rate = max(1, $this->exchangeRate);
        $usd = ceil($vnd / $rate * 1_000_000) / 1_000_000;

        return sprintf('%.6F', $usd);
    }

    /**
     * Gọi API napxu và chuẩn hoá lỗi.
     * napxu trả HTTP 401 cho key sai, 400/404 kèm {"error": "..."} cho lỗi nghiệp vụ.
     */
    private function call(string $method, string $path, array $payload = [], array $query = []): array
    {
        try {
            $request = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->withToken($this->apiKey);

            $url = rtrim($this->baseUrl, '/') . $path;

            $response = $method === 'GET'
                ? $request->get($url, $query)
                : $request->asJson()->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::warning('Napxu: không kết nối được', ['path' => $path, 'reason' => $e->getMessage()]);

            throw new ProviderException('Không kết nối được nhà cung cấp napxu.');
        }

        // 401: key sai định dạng / vô hiệu hoá — thông điệp riêng, không kèm key.
        if ($response->status() === 401) {
            throw new ProviderException('API key napxu không hợp lệ hoặc đã bị vô hiệu hoá.');
        }

        $data = $response->json();

        if ($response->failed()) {
            $message = is_array($data) && isset($data['error']) && is_string($data['error']) && $data['error'] !== ''
                ? $data['error']
                : 'Nhà cung cấp napxu trả lỗi HTTP ' . $response->status() . '.';

            Log::warning('Napxu: HTTP lỗi', ['path' => $path, 'status' => $response->status(), 'message' => $message]);

            throw new ProviderException($message);
        }

        if (! is_array($data)) {
            throw new ProviderException('Phản hồi nhà cung cấp napxu không phải JSON hợp lệ.');
        }

        $error = $data['error'] ?? false;

        if ($error !== false && $error !== null && $error !== '') {
            $message = is_string($error) ? $error : 'Nhà cung cấp napxu báo lỗi.';

            Log::warning('Napxu: báo lỗi nghiệp vụ', ['path' => $path, 'message' => $message]);

            throw new ProviderException($message);
        }

        return $data;
    }
}
