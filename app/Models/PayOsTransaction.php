<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayOsTransaction extends Model
{
    protected $fillable = [
        'deposit_request_id', 'user_id', 'order_code', 'payment_link_id',
        'checkout_url', 'qr_code', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['order_code' => 'integer', 'paid_at' => 'datetime'];
    }
}
