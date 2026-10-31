<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\RefundLimitExceededException;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Sổ cái ví — CỬA DUY NHẤT thay đổi số dư.
 *
 * Bất biến:
 *  1. Mọi thay đổi đi qua apply(), không UPDATE users.balance ở nơi khác.
 *  2. Khoá dòng user (SELECT ... FOR UPDATE) trước khi đọc/ghi số dư.
 *  3. idempotency_key UNIQUE — cùng key chỉ áp dụng một lần, kể cả khi
 *     webhook gửi lại, cron chạy chồng, hay người dùng bấm hai lần.
 *  4. Số dư không bao giờ âm.
 *  5. Hoàn tiền không bao giờ vượt cap (do RefundService truyền vào).
 */
class WalletService
{
    public function apply(
        int $userId,
        string $type,
        int $amount,
        string $idempotencyKey,
        ?string $note = null,
        ?int $orderId = null,
        ?int $depositId = null,
        ?int $refundCapRemaining = null
    ): WalletTransaction {
        // Chặn sớm: key đã dùng thì trả về giao dịch cũ (không cần vào transaction).
        $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use (
                $userId, $type, $amount, $idempotencyKey, $note, $orderId, $depositId, $refundCapRemaining
            ) {
                /** @var User $user */
                $user = User::query()->lockForUpdate()->findOrFail($userId);

                // Kiểm tra cap hoàn tiền (đơn hàng không được hoàn vượt tổng đã trả).
                if ($type === WalletTransaction::TYPE_REFUND && $refundCapRemaining !== null) {
                    if ($amount > $refundCapRemaining) {
                        throw new RefundLimitExceededException($amount, $refundCapRemaining);
                    }
                }

                $newBalance = $user->balance + $amount;

                if ($newBalance < 0) {
                    throw new InsufficientBalanceException(abs($amount), $user->balance);
                }

                $user->forceFill(['balance' => $newBalance])->save();

                return WalletTransaction::create([
                    'user_id' => $user->id,
                    'order_id' => $orderId,
                    'deposit_id' => $depositId,
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'idempotency_key' => $idempotencyKey,
                    'note' => $note,
                    'created_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Trường hợp hiếm: hai request cùng key lọt qua bước kiểm tra đầu tiên.
            // Bên thua nhận lỗi UNIQUE → trả về giao dịch của bên thắng.
            if ($this->isUniqueViolation($e)) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
