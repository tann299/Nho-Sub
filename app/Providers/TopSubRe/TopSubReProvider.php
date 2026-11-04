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

    public function addOrder(string $serviceId, string $link, int $quantity, ?string $reference = null): ProviderOrderResult
    {
        // $reference không dùng với TopSubRe — API V2 không có trường đối chiếu.
        unset($reference);

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
            $request = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->asJson();

            // Cho phép ghim IP qua cấu hình khi DNS của hosting bị lọc
            // (ví dụ Fortinet/ISP trả về trang chặn thay vì IP thật).
            $pinnedIp = (string) (\App\Models\Setting::query()
                ->where('key', 'topsubre_pinned_ip')->value('value') ?? '');

            $response = null;

            if ($pinnedIp !== '' && preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $pinnedIp)) {
                $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
                $payload = json_encode(array_merge(['key' => $this->apiKey, 'action' => $action], $params));

                // Gọi bằng cURL thuần để ghim IP (Guzzle không nhận CURLOPT_RESOLVE qua options).
                $ch = curl_init($this->endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => self::TIMEOUT,
                    CURLOPT_RESOLVE => ["{$host}:443:{$pinnedIp}"],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                $body = curl_exec($ch);
                $curlError = curl_error($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($body === false || $curlError !== '') {
                    throw new ConnectionException('cURL: ' . $curlError);
                }

                $data = json_decode((string) $body, true);

                if ($status >= 400) {
                    if ($status === 403 && str_contains((string) $body, 'Blocked')) {
                        throw new ProviderException(
                            'Truy cập bị chặn bởi bộ lọc mạng (IP ghim chưa đúng hoặc hết hiệu lực). '
                            . 'Lấy IP mới tại dns.google rồi cập nhật Cấu hình.'
                        );
                    }

                    throw new ProviderException('Nhà cung cấp trả lỗi HTTP ' . $status . '.');
                }

                if (! is_array($data)) {
                    throw new ProviderException('Phản hồi nhà cung cấp không phải JSON hợp lệ.');
                }

                return $this->assertNoError($data, $action);
            }

            $response = $request->post($this->endpoint, array_merge([
                'key' => $this->apiKey,
                'action' => $action,
            ], $params));
        } catch (ConnectionException $e) {
            Log::warning('TopSubRe: không kết nối được', ['action' => $action, 'reason' => $e->getMessage()]);

            // Phân biệt lỗi DNS bị chặn với lỗi mạng thường — thông điệp hướng dẫn admin.
            $hint = str_contains($e->getMessage(), 'certificate')
                || str_contains($e->getMessage(), 'SSL')
                || str_contains($e->getMessage(), 'resolve')
                    ? ' DNS của hosting có thể đang bị lọc domain. Vào Cấu hình nhập "IP ghim TopSubRe" (lấy IP thật qua dns.google) hoặc liên hệ nhà cung cấp mở IP.'
                    : '';

            throw new ProviderException('Không kết nối được nhà cung cấp.' . $hint);
        }

        if ($response->failed()) {
            Log::warning('TopSubRe: HTTP lỗi', ['action' => $action, 'status' => $response->status()]);

            // 403 từ trang chặn (Fortinet) không phải lỗi key — hướng dẫn rõ.
            if ($response->status() === 403 && str_contains((string) $response->body(), 'Blocked')) {
                throw new ProviderException(
                    'Truy cập bị chặn bởi bộ lọc mạng của hosting/ISP (không phải lỗi API key). '
                    . 'Vào Cấu hình nhập "IP ghim TopSubRe" hoặc nhờ nhà cung cấp mạng mở domain likenhanh.pro.'
                );
            }

            throw new ProviderException('Nhà cung cấp trả lỗi HTTP ' . $response->status() . '.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new ProviderException('Phản hồi nhà cung cấp không phải JSON hợp lệ.');
        }

        return $this->assertNoError($data, $action);
    }

    /** Kiểm tra trường `error` / `status=error` của phản hồi, ném lỗi kèm thông điệp. */
    private function assertNoError(array $data, string $action): array
    {
        $error = $data['error'] ?? false;

        // Một số endpoint trả {status: "error", msg: "..."} thay vì {error: "..."}.
        if ($error === false && ($data['status'] ?? null) === 'error') {
            $error = $data['msg'] ?? 'Nhà cung cấp báo lỗi.';
        }

        if ($error !== false && $error !== null && $error !== '' && $error !== 0) {
            // Chỉ lấy thông điệp lỗi; tuyệt đối không đưa key vào.
            $message = is_string($error) ? $error : 'Nhà cung cấp báo lỗi.';

            Log::warning('TopSubRe: báo lỗi nghiệp vụ', ['action' => $action, 'message' => $message]);

            throw new ProviderException($message);
        }

        return $data;
    }
}
