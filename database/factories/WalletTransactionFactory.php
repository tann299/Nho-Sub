<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => WalletTransaction::TYPE_DEPOSIT,
            'amount' => 10000,
            'balance_after' => 10000,
            'idempotency_key' => 'test:' . Str::uuid(),
            'created_at' => now(),
        ];
    }
}
