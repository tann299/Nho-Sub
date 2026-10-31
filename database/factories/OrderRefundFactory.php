<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderRefundFactory extends Factory
{
    protected $model = OrderRefund::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'seq' => 1,
            'amount' => 1000,
            'reason' => 'auto_partial',
        ];
    }
}
