<?php

namespace Database\Factories;

use App\Models\DepositRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepositRequestFactory extends Factory
{
    protected $model = DepositRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 50000,
            'method' => DepositRequest::METHOD_BANK,
            'transfer_code' => 'NHOSUB TESTUSER',
            'status' => DepositRequest::STATUS_PENDING,
            'created_at' => now(),
        ];
    }
}
