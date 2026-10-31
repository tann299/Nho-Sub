<?php

namespace App\Services\PayOS;

use App\Exceptions\ProviderException;
use App\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client PayOS — tạo link thanh toán + xác thực chữ ký webhook.
 * Key lưu dạng mã hoá ở settings, không bao giờ log.
 * README ghi rõ: cần tài khoản PayOS trước khi bật cổng này.
 */
class PayOSClient
{
    private const BASE_URL = 'https://api-merchant.payos.vn/v2/payment-requests';

    public function __construct(private readonly SettingService $settings)
    {
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('payos_enabled', false)
            && $this->settings->hasSecret('payos_client_id')
            && $this->settings->hasSecret('payos_api_key')
            && $this->settings->hasSecret('payos_checksum_key');
    }

    /**
     * @return array{checkoutUrl: string, qrCode: string, paymentLinkId: string}
     */
    public function createPaymentLink(
        int $orderCode,
        int $amount,
        string $description,
        string $returnUrl,
        string $cancelUrl
    ): array {
        $clientId = $this->settings->getSecret('payos_client_id');
        $apiKey = $this->settings->getSecret('payos_api_key');
        $checksum = $this->settings->getSecret('payos_checksum_key');

        if (! $clientId || ! $apiKey || ! $checksum) {
            throw new ProviderException('Chưa cấu hình PayOS.');
        }

        // Chữ ký theo tài liệu PayOS: sắp xếp key theo alphabet, nối value bằng dấu "|".
        $data = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => mb_substr($description, 0, 25),
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
        ];
        ksort($data);
        $signature = $this->sign($data, $checksum);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'x-client-id' => $clientId,
                    'x-api-key' => $apiKey,
                ])
                ->post(self::BASE_URL, array_merge($data, ['signature' => $signature]));
        } catch (\Throwable $e) {
            Log::warning('PayOS: không kết nối được');

            throw new ProviderException('Không kết nối được PayOS. Vui lòng thử lại.');
        }

        $json = $response->json();

        if (! is_array($json) || ($json['code'] ?? '') !== '00' || empty($json['data'])) {
            Log::warning('PayOS: tạo link thất bại', ['code' => $json['code'] ?? null]);

            throw new ProviderException($json['desc'] ?? 'PayOS từ chối tạo link thanh toán.');
        }

        return [
            'checkoutUrl' => (string) ($json['data']['checkoutUrl'] ?? ''),
            'qrCode' => (string) ($json['data']['qrCode'] ?? ''),
            'paymentLinkId' => (string) ($json['data']['paymentLinkId'] ?? ''),
        ];
    }

    /**
     * Xác thực chữ ký webhook PayOS.
     * PayOS gửi {code, desc, data:{...}, signature}. Ta ký lại data theo
     * checksum key và so sánh bằng hash_equals.
     */
    public function verifyWebhook(array $payload): bool
    {
        $checksum = $this->settings->getSecret('payos_checksum_key');

        if (! $checksum) {
            return false;
        }

        $signature = (string) ($payload['signature'] ?? '');
        $data = $payload['data'] ?? null;

        if ($signature === '' || ! is_array($data)) {
            return false;
        }

        return hash_equals($this->sign($data, $checksum), $signature);
    }

    /** Ký dữ liệu: sắp xếp key, nối value bằng "|", HMAC-SHA256. */
    private function sign(array $data, string $checksumKey): string
    {
        ksort($data);

        $payload = implode('|', array_map(
            fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v,
            $data
        ));

        return hash_hmac('sha256', $payload, $checksumKey);
    }
}
