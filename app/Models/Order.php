<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CHARGED = 'charged';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_ERROR = 'error';
    public const STATUS_STUCK_REVIEW = 'stuck_review';

    /** Trạng thái đã kết thúc — không đồng bộ lại. */
    public const FINAL_STATUSES = [
        self::STATUS_COMPLETED, self::STATUS_CANCELED, self::STATUS_ERROR,
    ];

    protected $fillable = [
        'user_id', 'service_id', 'client_token', 'provider_order_id',
        'target_link', 'quantity', 'unit_price', 'total_amount', 'cost_amount',
        'status', 'start_count', 'remains', 'refunded_amount', 'refund_count',
        'error_message', 'synced_at', 'sync_attempts',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total_amount' => 'integer',
            'cost_amount' => 'integer',
            'start_count' => 'integer',
            'remains' => 'integer',
            'refunded_amount' => 'integer',
            'refund_count' => 'integer',
            'sync_attempts' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    /** Phần chưa chạy — kẹp trong 0..quantity để NCC trả số vô lý không phá hoàn tiền. */
    public function unusedQuantity(): int
    {
        $unused = $this->remains ?? ($this->quantity - (int) $this->start_count);

        return max(0, min((int) $unused, (int) $this->quantity));
    }
}
