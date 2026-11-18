<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tài khoản TikTok của khách — đại diện cho TK đã liên kết vào napxu.
 * Mỗi TK thuộc về 1 user; khách chỉ thấy TK của mình.
 */
class TikTokAccount extends Model
{
    use SoftDeletes;

    protected $table = 'tiktok_accounts';

    protected $fillable = [
        'user_id', 'napxu_account_id', 'username', 'nickname', 'avatar',
        'coins', 'account_status', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'napxu_account_id' => 'integer',
            'coins' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
