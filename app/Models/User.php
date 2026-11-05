<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_LOCKED = 'locked';

    // BẢO MẬT: không đặt role/balance/status vào fillable — chúng chỉ được
    // thay đổi qua code paths có kiểm soát (register hard-code customer,
    // admin adjust qua route riêng có CSRF + confirm). Chống mass assignment.
    protected $fillable = [
        'username', 'email', 'password_hash', 'full_name', 'phone',
        'last_login_at',
    ];

    protected $guarded = ['id', 'role', 'balance', 'status', 'failed_login_attempts', 'locked_until', 'email_verified_at', 'created_at', 'updated_at'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    /** Laravel xác thực dùng cột password_hash thay vì password mặc định. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /** Mã nội dung chuyển khoản riêng của khách: "NHOSUB <USERNAME>". */
    public function transferContent(): string
    {
        return 'NHOSUB ' . strtoupper($this->username);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function depositRequests(): HasMany
    {
        return $this->hasMany(DepositRequest::class);
    }
}
