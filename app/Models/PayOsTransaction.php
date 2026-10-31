<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayOsTransaction extends Model
{
    // Eloquent suy tên bảng "pay_os_transactions" — sai so với migration.
    protected $table = 'payos_transactions';

    protected $fillable = [
        'deposit_request_id', 'user_id', 'order_code', 'payment_link_id',
        'checkout_url', 'qr_code', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['order_code' => 'integer', 'paid_at' => 'datetime'];
    }
}
