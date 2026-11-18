<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Gói nạp xu — admin nhập tay theo giá napxu.vn (API không public DS gói).
 * Giá đóng băng vào topup_orders khi đặt, sửa giá không ảnh hưởng đơn cũ.
 */
class TopupPackage extends Model
{
    protected $fillable = [
        'name', 'coins', 'cost_price', 'sell_price', 'server',
        'napxu_package_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'coins' => 'integer',
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'napxu_package_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
