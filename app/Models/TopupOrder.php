<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đơn nạp xu TikTok qua napxu. Trạng thái napxu trả:
 * pending → processing → success | failed | refund (huỷ pending → canceled nội bộ).
 */
class TopupOrder extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_CHARGED = 'charged';
    public const STATUS_STUCK_REVIEW = 'stuck_review';

    /** Trạng thái napxu cần cron theo dõi tiếp. */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_PROCESSING];

    protected $fillable = [
        'user_id', 'tiktok_account_id', 'topup_package_id', 'coins',
        'sell_price', 'cost_price', 'napxu_order_ref', 'status',
        'synced_at', 'synced_count', 'client_token', 'napxu_order_id',
        'server', 'napxu_package_id', 'refunded_amount', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'sell_price' => 'integer',
            'refunded_amount' => 'integer',
            'napxu_order_id' => 'integer',
            'napxu_package_id' => 'integer',
            'cost_price' => 'integer',
            'synced_count' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TikTokAccount::class, 'tiktok_account_id')->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_REFUNDED, self::STATUS_CANCELED], true);
    }
}
