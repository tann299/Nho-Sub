<?php

namespace App\Services;

use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Xử lý nạp tiền từ mọi nguồn: webhook cổng thanh toán, duyệt tay, admin cộng tiền.
 *
 * Điểm then chốt: webhook có thể bị gửi lại nhiều lần. Ta chặn bằng
 * UNIQUE(gateway, gateway_txn_id) ở webhook_logs VÀ idempotency_key
 * ở wallet_transactions — hai lớp độc lập.
 */
class DepositService
{
    // "NHOSUB" + khoảng trắng tuỳ chọn + username, kết thúc bằng ranh giới từ.
    // \s* cho phép "NHOSUBABC"; \b ở cuối tránh cắt "KHACHVIP" thành "KHACH".
    private const CONTENT_PATTERN = '/\bNHOSUB\s*([A-Za-z0-9_]{3,32})\b/iu';

    // Mã xác thực: 6 ký tự không dễ nhầm (bỏ 0/O, 1/I) — nằm sau username trong nội dung CK.
    private const CODE_PATTERN = '/\b([A-HJ-NP-Z2-9]{6})\b/u';

    // Bảng ký tự sinh mã — bỏ 0/O, 1/I để khách gõ tay không nhầm.
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly WalletService $wallet,
        private readonly SettingService $settings,
    ) {
    }

    /** Sinh mã xác thực ngẫu nhiên 6 ký tự cho một yêu cầu nạp. */
    public function generateVerificationCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $length = mb_strlen($alphabet);
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, $length - 1)];
        }

        // Mã đang sống (chưa duyệt/hết hạn) trùng nhau rất hiếm — nhưng vẫn kiểm tra.
        if (DepositRequest::where('verification_code', $code)
            ->whereIn('status', [DepositRequest::STATUS_PENDING])
            ->exists()) {
            return $this->generateVerificationCode();
        }

        return $code;
    }

    /**
     * Tách username từ nội dung chuyển khoản.
     * Chấp nhận: "NHOSUB ABC", "NHOSUBABC", "nhosub  abc ...".
     */
    public function parseTransferContent(string $content): ?string
    {
        if (! preg_match(self::CONTENT_PATTERN, $content, $m)) {
            return null;
        }

        return mb_strtolower($m[1]);
    }

    /**
     * Tách mã xác thực từ nội dung chuyển khoản (nếu có).
     * "NHOSUB KHACHVIP N7K2P9" → "N7K2P9". Trả null nếu không có.
     */
    public function parseVerificationCode(string $content): ?string
    {
        if (! preg_match(self::CODE_PATTERN, $content, $m)) {
            return null;
        }

        return strtoupper($m[1]);
    }

    /**
     * Tìm user theo nội dung chuyển khoản — ưu tiên MÃ XÁC THỰC, fallback username.
     *
     * Mã xác thực chính xác 1-1 với yêu cầu nạp nên two khách chuyển cùng lúc
     * không bao giờ nhầm nhau. Username chỉ dùng khi khách quên không kèm mã.
     *
     * @return array{user: ?User, matchedBy: string, expectedCode: ?string, depositRequest: ?DepositRequest}
     */
    public function resolveDepositUser(string $content, int $amount): array
    {
        $code = $this->parseVerificationCode($content);
        $expectedCode = null;

        // 1) Ưu tiên mã xác thực — chính xác nhất.
        if ($code !== null) {
            $deposit = DepositRequest::where('verification_code', $code)
                ->whereIn('status', [DepositRequest::STATUS_PENDING])
                ->first();

            if ($deposit !== null) {
                // Số tiền phải khớp yêu cầu (không quá lệch 10% cho phí bank).
                $expected = (int) $deposit->amount;
                $ok = $amount >= $expected && $amount <= (int) ceil($expected * 1.1);

                if ($ok) {
                    return [
                        'user' => $deposit->user,
                        'matchedBy' => 'code',
                        'expectedCode' => $code,
                        'depositRequest' => $deposit,
                    ];
                }
            }

            $expectedCode = $code; // log để admin thấy mã khách chuyển nhưng không khớp
        }

        // 2) Fallback: username trong nội dung (cách cũ).
        $username = $this->parseTransferContent($content);

        if ($username !== null) {
            $user = User::whereRaw('LOWER(username) = ?', [$username])->first();

            if ($user !== null) {
                return [
                    'user' => $user,
                    'matchedBy' => 'username',
                    'expectedCode' => $expectedCode,
                    'depositRequest' => null,
                ];
            }
        }

        return ['user' => null, 'matchedBy' => 'none', 'expectedCode' => $expectedCode, 'depositRequest' => null];
    }

    /**
     * Xử lý một giao dịch tiền vào từ cổng thanh toán.
     *
     * @return array{status: string, message: string}
     */
    public function handleGatewayDeposit(
        string $gateway,
        string $gatewayTxnId,
        int $amount,
        string $content,
        array $payload,
        bool $isTransferIn = true
    ): array {
        $minDeposit = $this->settings->getInt('min_deposit', 10000);

        // Ghi nhật ký trước (dedupe ở đây), payload đã che key.
        $log = $this->logWebhook($gateway, $gatewayTxnId, $amount, $content, $payload);

        if ($log === null) {
            return ['status' => WebhookLog::STATUS_DUPLICATE, 'message' => 'Giao dịch đã được xử lý trước đó.'];
        }

        if (! $isTransferIn) {
            $this->markLog($log, WebhookLog::STATUS_REJECTED, 'Không phải giao dịch tiền vào.');

            return ['status' => WebhookLog::STATUS_REJECTED, 'message' => 'Chỉ xử lý giao dịch tiền vào.'];
        }

        if ($amount < $minDeposit) {
            $this->markLog(
                $log,
                WebhookLog::STATUS_REJECTED,
                'Số tiền dưới mức tối thiểu ' . number_format($minDeposit, 0, ',', '.') . ' ₫.'
            );

            return ['status' => WebhookLog::STATUS_REJECTED, 'message' => 'Số tiền dưới mức tối thiểu.'];
        }

        // Đối chiếu: ưu tiên mã xác thực, fallback username.
        $resolved = $this->resolveDepositUser($content, $amount);
        $user = $resolved['user'];

        if ($user === null) {
            $this->markLog($log, WebhookLog::STATUS_UNMATCHED,
                $resolved['expectedCode'] !== null
                    ? "Mã xác thực '{$resolved['expectedCode']}' không khớp yêu cầu nạp nào (hoặc sai số tiền)."
                    : 'Nội dung không chứa mã NHOSUB <tài khoản>.',
                $resolved['expectedCode']);

            return ['status' => WebhookLog::STATUS_UNMATCHED, 'message' => 'Không tìm thấy mã chuyển khoản.'];
        }

        // Ghi nhận nạp tiền + cộng ví trong 1 transaction.
        DB::transaction(function () use ($user, $amount, $gateway, $gatewayTxnId, $log, $resolved) {
            // Có yêu cầu nạp sẵn (khớp mã xác thực) → duyệt luôn yêu cầu đó.
            $deposit = $resolved['depositRequest'];

            if ($deposit === null) {
                $deposit = DepositRequest::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'method' => $gateway,
                    'transfer_code' => $user->transferContent(),
                    'status' => DepositRequest::STATUS_APPROVED,
                    'gateway_txn_id' => $gatewayTxnId,
                    'created_at' => now(),
                    'reviewed_at' => now(),
                ]);
            } else {
                $deposit->forceFill([
                    'status' => DepositRequest::STATUS_APPROVED,
                    'gateway_txn_id' => $gatewayTxnId,
                    'reviewed_at' => now(),
                ])->save();
            }

            $this->wallet->apply(
                userId: (int) $user->id,
                type: WalletTransaction::TYPE_DEPOSIT,
                amount: $amount,
                idempotencyKey: "deposit:{$gateway}:{$gatewayTxnId}",
                note: "Nạp tiền qua {$gateway}",
                depositId: $deposit->id,
            );

            $this->markLog($log, WebhookLog::STATUS_PROCESSED, null, $resolved['expectedCode']);
        });

        return ['status' => WebhookLog::STATUS_PROCESSED, 'message' => 'Đã cộng tiền.'];
    }

    /** Ghi log webhook; trả null nếu giao dịch đã tồn tại (dedupe). */
    private function logWebhook(
        string $gateway,
        string $gatewayTxnId,
        int $amount,
        string $content,
        array $payload
    ): ?WebhookLog {
        $exists = WebhookLog::where('gateway', $gateway)
            ->where('gateway_txn_id', $gatewayTxnId)
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            return WebhookLog::create([
                'gateway' => $gateway,
                'gateway_txn_id' => $gatewayTxnId,
                'amount' => $amount,
                'content' => mb_substr($content, 0, 255),
                'status' => WebhookLog::STATUS_UNMATCHED,
                'payload' => $this->maskPayload($payload),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Hai webhook cùng lúc: bên thua coi như trùng.
            if ((int) $e->getCode() === 23000) {
                return null;
            }

            throw $e;
        }
    }

    private function markLog(WebhookLog $log, string $status, ?string $reason, ?string $expectedCode = null): void
    {
        $log->forceFill(['status' => $status, 'reason' => $reason, 'expected_code' => $expectedCode])->save();
    }

    /** Che mọi trường có tên chứa key/token/secret trước khi lưu nhật ký. */
    private function maskPayload(array $payload): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            if (preg_match('/key|token|secret|password|signature/i', (string) $key)) {
                $masked[$key] = '***';
                continue;
            }

            $masked[$key] = is_array($value) ? $this->maskPayload($value) : $value;
        }

        return $masked;
    }

    /** Tạo yêu cầu nạp duyệt tay. Mỗi khách tối đa 1 yêu cầu tay đang chờ. */
    public function createManualRequest(int $userId, int $amount, string $method = 'bank', ?string $note = null): DepositRequest
    {
        $minDeposit = $this->settings->getInt('min_deposit', 10000);

        if ($amount < $minDeposit) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền nạp tối thiểu là ' . number_format($minDeposit, 0, ',', '.') . ' ₫.',
            ]);
        }

        $hasPending = DepositRequest::where('user_id', $userId)
            ->where('method', DepositRequest::METHOD_BANK)
            ->where('status', DepositRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'amount' => 'Bạn đang có một yêu cầu nạp chờ duyệt. Vui lòng chờ admin xử lý.',
            ]);
        }

        $user = User::findOrFail($userId);

        return DepositRequest::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'method' => $method,
            'transfer_code' => $user->transferContent(),
            'verification_code' => $this->generateVerificationCode(),
            'status' => DepositRequest::STATUS_PENDING,
            'admin_note' => $note,
            'created_at' => now(),
        ]);
    }

    /** Admin duyệt nạp tay — cộng tiền đúng một lần. */
    public function approveManual(DepositRequest $request, int $adminId, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $adminId, $note) {
            $locked = DepositRequest::query()->lockForUpdate()->findOrFail($request->id);

            // Đã duyệt rồi thì không làm gì (chống bấm hai lần).
            if ($locked->status !== DepositRequest::STATUS_PENDING) {
                return;
            }

            $locked->forceFill([
                'status' => DepositRequest::STATUS_APPROVED,
                'admin_note' => $note ?? $locked->admin_note,
                'reviewed_at' => now(),
            ])->save();

            $this->wallet->apply(
                userId: (int) $locked->user_id,
                type: WalletTransaction::TYPE_DEPOSIT,
                amount: (int) $locked->amount,
                idempotencyKey: "deposit:manual:{$locked->id}",
                note: 'Duyệt nạp tiền #' . $locked->id . ' bởi admin #' . $adminId,
                depositId: (int) $locked->id,
            );
        });
    }

    /** Admin từ chối — bắt buộc có lý do. */
    public function rejectManual(DepositRequest $request, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'admin_note' => 'Vui lòng nhập lý do từ chối.',
            ]);
        }

        $locked = DepositRequest::query()->lockForUpdate()->findOrFail($request->id);

        if ($locked->status !== DepositRequest::STATUS_PENDING) {
            return;
        }

        $locked->forceFill([
            'status' => DepositRequest::STATUS_REJECTED,
            'admin_note' => $reason,
            'reviewed_at' => now(),
        ])->save();
    }

    /** Đóng các yêu cầu nạp cổng thanh toán quá hạn (mặc định 30 phút). */
    public function expireStaleRequests(): int
    {
        return DepositRequest::where('status', DepositRequest::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => DepositRequest::STATUS_EXPIRED]);
    }
}
