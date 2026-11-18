<?php

namespace App\Services\Napxu;

use App\Exceptions\NapxuException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class NapxuClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://napxu.vn/api',
    ) {
        // Chỉ gửi credential tới Napxu chính thức; không cho cấu hình URL thành SSRF.
        if (rtrim($baseUrl, '/') !== 'https://napxu.vn/api') {
            throw new InvalidArgumentException('URL API Napxu không hợp lệ.');
        }
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $path, $payload);
    }

    private function request(string $method, string $path, array $data): array
    {
        if ($this->apiKey === '') {
            throw new NapxuException('Chưa cấu hình API key Napxu.');
        }
        if (! preg_match('#^/[a-zA-Z0-9_/-]+$#D', $path)) {
            throw new InvalidArgumentException('Đường dẫn API Napxu không hợp lệ.');
        }

        try {
            $request = Http::acceptJson()->withToken($this->apiKey)
                ->connectTimeout(5)->timeout(20)->withoutRedirecting();
            // Không retry POST: Napxu không hỗ trợ idempotency phía nhà cung cấp.
            $response = $method === 'GET'
                ? $request->get(rtrim($this->baseUrl, '/').$path, $data)
                : $request->asJson()->post(rtrim($this->baseUrl, '/').$path, $data);
        } catch (ConnectionException) {
            throw new NapxuException('Không kết nối được Napxu; cần đối soát kết quả.', ambiguous: true);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new NapxuException('API key hoặc quyền truy cập Napxu không hợp lệ.');
        }
        if ($response->serverError() || $response->redirect()) {
            throw new NapxuException('Napxu chưa trả kết quả xác định; cần đối soát.', ambiguous: true);
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new NapxuException('Phản hồi Napxu không hợp lệ; cần đối soát.', ambiguous: true);
        }
        if ($response->failed() || ! empty($body['error'])) {
            $message = is_string($body['error'] ?? null) ? $body['error'] : 'Napxu từ chối yêu cầu.';
            foreach ([$this->apiKey, $data['cookie'] ?? '', $data['pin'] ?? ''] as $secret) {
                if (is_string($secret) && $secret !== '') {
                    $message = str_replace($secret, '[đã che]', $message);
                }
            }
            $message = preg_replace('/napx_[a-zA-Z0-9_]+/', '[đã che]', $message);
            throw new NapxuException(mb_substr(strip_tags($message), 0, 300));
        }

        return $body;
    }
}
