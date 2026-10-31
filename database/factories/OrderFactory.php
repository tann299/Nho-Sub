<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'client_token' => (string) Str::uuid(),
            'provider_order_id' => null,
            'target_link' => 'https://facebook.com/posts/' . fake()->numerify('#########'),
            'quantity' => 1000,
            'unit_price' => 3380,
            'total_amount' => 3380,
            'cost_amount' => 2600,
            'status' => Order::STATUS_PROCESSING,
        ];
    }
}
