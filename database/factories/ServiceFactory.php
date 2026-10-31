<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'provider_service_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'cost_usd' => '0.100000',
            'cost_price' => 2600,
            'sell_price' => 3380,
            'sell_price_override' => false,
            'min_qty' => 100,
            'max_qty' => 100000,
            'speed' => '10K/ngày',
            'avg_start_time' => '0-30 phút',
            'is_active' => true,
        ];
    }
}
