<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSED_LATE = 'processed_late';

    protected $fillable = [
        'gateway', 'gateway_txn_id', 'amount', 'content', 'status', 'reason', 'payload',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'payload' => 'array'];
    }
}
