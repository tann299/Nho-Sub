<?php

namespace App\Services\Napxu;

use App\Exceptions\NapxuException;
use App\Models\TikTokAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TikTokAccountService
{
    public function __construct(private readonly NapxuClient $client)
    {
    }

    public function createQrSession(int $userId): array
    {
        $data = $this->client->post('/auth/tiktok/qr/generate');
        $id = $data['sessionId'] ?? null;
        $image = $data['qrImage'] ?? null;
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{16,128}$/D', $id)
            || ! is_string($image) || strlen($image) > 1_000_000
            || ! preg_match('#^data:image/png;base64,[a-zA-Z0-9+/=]+$#D', $image)) {
            throw new NapxuException('Napxu trả mã QR không hợp lệ.');
        }

        $ttl = max(1, min(300, (int) ($data['timeRemaining'] ?? 180)));
        $expiresAt = now()->timestamp + $ttl;
        Cache::put('napxu.qr.'.$id, ['user_id' => $userId, 'expires_at' => $expiresAt], $ttl);

        return ['sessionId' => $id, 'qrImage' => $image, 'expiresAt' => $expiresAt];
    }

    public function pollQrSession(int $userId, string $sessionId): array
    {
        $session = Cache::get('napxu.qr.'.$sessionId);
        if (! is_array($session) || ($session['user_id'] ?? null) !== $userId) {
            throw ValidationException::withMessages(['qr' => 'Phiên QR hết hạn hoặc không thuộc tài khoản của bạn.']);
        }
        if (isset($session['result'])) {
            return $session['result'];
        }

        $data = $this->client->get('/auth/tiktok/qr/status/'.$sessionId);
        $status = $data['status'] ?? '';
        if (! in_array($status, ['pending', 'scanned', 'done', 'expired', 'error'], true)) {
            throw new NapxuException('Napxu trả trạng thái QR không hợp lệ.');
        }
        $result = ['status' => $status];
        if ($status === 'done') {
            $account = $this->saveAccount($userId, $data['account'] ?? []);
            $result['account'] = $account->only(['id', 'username', 'nickname', 'avatar', 'coins', 'account_status']);
        } elseif ($status === 'error') {
            $result['message'] = 'Liên kết TikTok không thành công. Vui lòng tạo mã QR mới.';
        }
        if (in_array($status, ['done', 'expired', 'error'], true)) {
            $session['result'] = $result;
            Cache::put('napxu.qr.'.$sessionId, $session, max(1, $session['expires_at'] - now()->timestamp));
        }

        return $result;
    }

    public function addByCookie(int $userId, string $cookie): TikTokAccount
    {
        $cookie = trim($cookie);
        if ($cookie === '' || strlen($cookie) > 20000) {
            throw ValidationException::withMessages(['cookie' => 'Cookie không hợp lệ hoặc quá dài.']);
        }
        $data = $this->client->post('/auth/tiktok/add', ['cookie' => $cookie]);

        // Cookie chỉ truyền tới Napxu; không lưu cache, DB, log hay response.
        return $this->saveAccount($userId, $data['account'] ?? []);
    }

    public function syncCoins(TikTokAccount $account): void
    {
        $page = 1;
        do {
            $data = $this->client->get('/auth/tiktok/accounts', [
                'search' => $account->username, 'page' => $page, 'limit' => 100,
            ]);
            if (! is_array($data['accounts'] ?? null)) {
                throw new NapxuException('Napxu trả danh sách tài khoản không hợp lệ.');
            }
            foreach ($data['accounts'] as $row) {
                if (is_array($row) && (string) ($row['id'] ?? '') === (string) $account->napxu_account_id) {
                    $account->forceFill($this->accountValues($row))->save();
                    return;
                }
            }
            $pages = max(1, (int) ($data['pagination']['totalPages'] ?? 1));
        } while (++$page <= min(50, $pages));

        throw new NapxuException('Không tìm thấy tài khoản TikTok trên Napxu. Vui lòng liên kết lại.');
    }

    private function saveAccount(int $userId, mixed $row): TikTokAccount
    {
        if (! is_array($row) || filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new NapxuException('Napxu trả tài khoản TikTok không hợp lệ.');
        }
        $values = $this->accountValues($row);
        // UNIQUE napxu_account_id chặn hai khách nhận cùng một tài khoản remote.
        $account = TikTokAccount::withTrashed()->firstOrCreate(
            ['napxu_account_id' => (int) $row['id']],
            ['user_id' => $userId] + $values,
        );
        if ((int) $account->user_id !== $userId) {
            throw ValidationException::withMessages(['account' => 'Tài khoản TikTok đã liên kết với khách khác.']);
        }
        $account->forceFill($values + ['deleted_at' => null])->save();

        return $account;
    }

    private function accountValues(array $row): array
    {
        $username = $row['username'] ?? null;
        $coins = filter_var($row['coins'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (! is_string($username) || ! preg_match('/^@?[a-zA-Z0-9_.]{1,64}$/D', $username) || $coins === false) {
            throw new NapxuException('Napxu trả thông tin TikTok không hợp lệ.');
        }
        $avatar = is_string($row['avatar'] ?? null) ? $row['avatar'] : '';
        if (! str_starts_with($avatar, 'https://') || strlen($avatar) > 500) {
            $avatar = '';
        }

        return [
            'username' => $username,
            'nickname' => mb_substr(is_string($row['nickname'] ?? null) ? $row['nickname'] : '', 0, 120),
            'avatar' => $avatar,
            'coins' => $coins,
            'account_status' => mb_substr(is_string($row['status'] ?? null) ? $row['status'] : 'active', 0, 30),
            'synced_at' => now(),
        ];
    }
}
