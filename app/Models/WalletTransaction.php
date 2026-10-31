<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_ORDER_CHARGE = 'order_charge';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'order_id', 'deposit_id', 'type',
        'amount', 'balance_after', 'idempotency_key', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
