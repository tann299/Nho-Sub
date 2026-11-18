<?php

namespace App\Services\Napxu;

use App\Exceptions\NapxuException;
use App\Models\TikTokAccount;
use App\Models\TopupOrder;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TopupOrderService
{
    public function __construct(private readonly WalletService $wallet, private readonly NapxuClient $client)
    {
    }

    public function placeOrder(User $user, TikTokAccount $account, TopupPackage $package, string $clientToken, ?string $pin = null): TopupOrder
    {
        if (! Str::isUuid($clientToken) || ($pin !== null && ! preg_match('/^\d{6}$/D', $pin))) {
            throw ValidationException::withMessages(['client_token' => 'Yêu cầu đặt đơn hoặc PIN không hợp lệ.']);
        }

        // Resolve credentials before debiting. A missing key is a definitive local error.
        try {
            $order = DB::transaction(function () use ($user, $account, $package, $clientToken) {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($lockedUser->isLocked()) {
                    throw ValidationException::withMessages(['account' => 'Tài khoản NhoSub đã bị khóa.']);
                }
                $existing = TopupOrder::where('client_token', $clientToken)->first();
                if ($existing !== null) {
                    return $this->checkReplay($existing, $user->id, $account->id, $package->id);
                }
                $linked = TikTokAccount::where('user_id', $user->id)->find($account->id);
                $offer = TopupPackage::where('is_active', true)->find($package->id);
                if ($linked === null) {
                    throw ValidationException::withMessages(['tiktok_account_id' => 'Tài khoản TikTok không thuộc bạn hoặc đã gỡ liên kết.']);
                }
                if ($offer === null || $offer->coins <= 0 || $offer->cost_price <= 0
                    || $offer->sell_price < $offer->cost_price || $offer->napxu_package_id <= 0
                    || ! preg_match('/^[a-zA-Z0-9_-]{1,30}$/D', $offer->server)) {
                    throw ValidationException::withMessages(['topup_package_id' => 'Gói nạp không hợp lệ hoặc đã ngừng bán.']);
                }
                $created = TopupOrder::create([
                    'user_id' => $user->id,
                    'tiktok_account_id' => $linked->id,
                    'topup_package_id' => $offer->id,
                    'client_token' => $clientToken,
                    'coins' => $offer->coins,
                    'sell_price' => $offer->sell_price,
                    'cost_price' => $offer->cost_price,
                    'server' => $offer->server,
                    'napxu_package_id' => $offer->napxu_package_id,
                    'status' => TopupOrder::STATUS_CHARGED,
                ]);
                $this->wallet->apply(
                    userId: $user->id,
                    type: WalletTransaction::TYPE_ORDER_CHARGE,
                    amount: -$created->sell_price,
                    idempotencyKey: "topup:{$created->id}:charge",
                    note: "Thanh toán nạp xu NX-{$created->id}",
                );

                return $created;
            });
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000' && ($existing = TopupOrder::where('client_token', $clientToken)->first())) {
                return $this->checkReplay($existing, $user->id, $account->id, $package->id);
            }
            throw $e;
        }

        // Only the transaction that created the order may send it to Napxu.
        if (! $order->wasRecentlyCreated) {
            return $order;
        }
        try {
            $payload = [
                'tiktok_account_id' => $account->napxu_account_id,
                'package_id' => $order->napxu_package_id,
                'coins' => $order->coins,
                'server' => $order->server,
            ];
            if ($pin !== null) {
                $payload['pin'] = $pin;
            }
            $data = $this->client->post('/topup/orders', $payload);
            $remote = $this->validateRemote($data['order'] ?? null, $order);
        } catch (NapxuException $e) {
            if ($e->ambiguous) {
                $order->forceFill(['status' => TopupOrder::STATUS_STUCK_REVIEW, 'error_message' => $e->getMessage()])->save();
            } else {
                $this->refund($order, TopupOrder::STATUS_FAILED, $e->getMessage());
            }

            return $order->fresh();
        }

        // Database failure after acceptance must NOT trigger a refund or a resend.
        $order->forceFill([
            'napxu_order_id' => (int) $remote['id'],
            'napxu_order_ref' => $remote['order_ref'],
            'status' => TopupOrder::STATUS_PENDING,
            'error_message' => null,
        ])->save();
        $this->applyRemoteStatus($order, (string) ($remote['status'] ?? 'pending'));

        return $order->fresh();
    }

    public function syncOne(TopupOrder $order): void
    {
        $order = $order->fresh();
        if ($order->isFinal() || $order->status === TopupOrder::STATUS_STUCK_REVIEW || ! $order->napxu_order_id) {
            return;
        }
        $data = $this->client->get('/topup/orders/'.$order->napxu_order_id);
        $remote = $this->validateRemote($data['order'] ?? null, $order, requireMatch: true);
        $this->applyRemoteStatus($order, (string) ($remote['status'] ?? ''));
    }

    public function cancelPending(TopupOrder $order): bool
    {
        $order = $order->fresh();
        if ($order->status !== TopupOrder::STATUS_PENDING || ! $order->napxu_order_id) {
            throw ValidationException::withMessages(['order' => 'Chỉ hủy đơn Napxu đã xác nhận đang chờ xử lý.']);
        }
        $this->client->post('/topup/orders/'.$order->napxu_order_id.'/cancel');
        // A cancellation response is not proof of refund. Read the confirmed state.
        $this->syncOne($order);

        return in_array($order->fresh()->status, [TopupOrder::STATUS_REFUNDED, TopupOrder::STATUS_CANCELED], true);
    }

    public function manualRefund(TopupOrder $order): void
    {
        $order = $order->fresh();
        if (! in_array($order->status, [TopupOrder::STATUS_STUCK_REVIEW, TopupOrder::STATUS_FAILED, TopupOrder::STATUS_REFUNDED, TopupOrder::STATUS_CANCELED], true)) {
            throw ValidationException::withMessages(['order' => 'Không hoàn tay đơn đã thành công hoặc đang chạy. Đối soát với Napxu trước.']);
        }
        $this->refund($order, TopupOrder::STATUS_REFUNDED, 'Admin xác nhận đối soát và hoàn tiền.');
    }

    /** Attach an admin-verified remote receipt to an unresolved order, without resending it. */
    public function reconcile(TopupOrder $order, int $remoteId): void
    {
        $order = $order->fresh();
        if ($order->status !== TopupOrder::STATUS_STUCK_REVIEW || $remoteId <= 0 || $order->refunded_amount > 0) {
            throw ValidationException::withMessages(['order' => 'Đơn không thuộc trạng thái chờ đối soát.']);
        }
        $data = $this->client->get('/topup/orders/'.$remoteId);
        $remote = $this->validateRemote($data['order'] ?? null, $order);
        $username = (string) ($remote['tiktok_username'] ?? '');
        if ($username === '' || ltrim($username, '@') !== ltrim($order->account->username, '@')
            || (int) ($remote['coins'] ?? 0) !== $order->coins) {
            throw ValidationException::withMessages(['napxu_order_id' => 'Biên nhận Napxu không khớp tài khoản và số xu của đơn.']);
        }
        DB::transaction(function () use ($order, $remote) {
            $locked = TopupOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== TopupOrder::STATUS_STUCK_REVIEW || $locked->refunded_amount > 0) {
                throw ValidationException::withMessages(['order' => 'Đơn đã được xử lý bởi thao tác khác.']);
            }
            $locked->forceFill(['napxu_order_id' => (int) $remote['id'], 'napxu_order_ref' => $remote['order_ref'],
                'status' => TopupOrder::STATUS_PENDING, 'error_message' => null])->save();
        });
        $this->applyRemoteStatus($order->fresh(), (string) ($remote['status'] ?? ''));
    }

    private function checkReplay(TopupOrder $order, int $userId, int $accountId, int $packageId): TopupOrder
    {
        if ((int) $order->user_id !== $userId || (int) $order->tiktok_account_id !== $accountId
            || (int) $order->topup_package_id !== $packageId) {
            throw ValidationException::withMessages(['client_token' => 'Mã xác nhận đã dùng cho yêu cầu khác.']);
        }

        return $order;
    }

    private function validateRemote(mixed $remote, TopupOrder $order, bool $requireMatch = false): array
    {
        if (! is_array($remote)
            || filter_var($remote['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            || ! is_string($remote['order_ref'] ?? null) || strlen($remote['order_ref']) > 64 || $remote['order_ref'] === ''
            || (isset($remote['coins']) && (int) $remote['coins'] !== $order->coins)
            || ($requireMatch && ((int) $remote['id'] !== $order->napxu_order_id || $remote['order_ref'] !== $order->napxu_order_ref))) {
            throw new NapxuException('Biên nhận Napxu không hợp lệ; cần đối soát.', ambiguous: true);
        }

        return $remote;
    }

    private function applyRemoteStatus(TopupOrder $order, string $status): void
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['refund', 'refunded', 'failed', 'canceled', 'cancelled'], true)) {
            $newStatus = $status === 'failed' ? TopupOrder::STATUS_FAILED : TopupOrder::STATUS_REFUNDED;
            $this->refund($order, $newStatus);
            return;
        }
        $mapped = match ($status) {
            'pending' => TopupOrder::STATUS_PENDING,
            'processing', 'in_progress', 'in progress' => TopupOrder::STATUS_PROCESSING,
            'success', 'completed' => TopupOrder::STATUS_SUCCESS,
            default => null,
        };
        if ($mapped === null) {
            throw new NapxuException('Trạng thái Napxu chưa xác định. Giữ đơn để đối soát.', ambiguous: true);
        }
        DB::transaction(function () use ($order, $mapped) {
            $locked = TopupOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->isFinal() || $locked->refunded_amount > 0) {
                return;
            }
            $locked->forceFill(['status' => $mapped, 'synced_at' => now(), 'synced_count' => $locked->synced_count + 1])->save();
        });
    }

    private function refund(TopupOrder $order, string $status, ?string $message = null): void
    {
        DB::transaction(function () use ($order, $status, $message) {
            // Match the placement lock order: user first, then order (prevents deadlocks).
            User::query()->lockForUpdate()->findOrFail($order->user_id);
            $locked = TopupOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === TopupOrder::STATUS_SUCCESS || $locked->refunded_amount >= $locked->sell_price) {
                return;
            }
            $charge = WalletTransaction::where('idempotency_key', "topup:{$locked->id}:charge")->first();
            if ($charge === null || (int) $charge->user_id !== (int) $locked->user_id || $charge->amount !== -$locked->sell_price) {
                throw ValidationException::withMessages(['order' => 'Không tìm thấy khoản trừ ví đúng với đơn. Cần đối soát.']);
            }
            $amount = $locked->sell_price - $locked->refunded_amount;
            $this->wallet->apply(
                userId: $locked->user_id,
                type: WalletTransaction::TYPE_REFUND,
                amount: $amount,
                idempotencyKey: "topup:{$locked->id}:refund",
                note: "Hoàn nạp xu NX-{$locked->id}",
                refundCapRemaining: $amount,
            );
            $locked->forceFill(['status' => $status, 'refunded_amount' => $locked->sell_price,
                'error_message' => $message, 'synced_at' => now(), 'synced_count' => $locked->synced_count + 1])->save();
        });
    }
}
