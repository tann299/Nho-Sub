<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => 'khach' . Str::lower(Str::random(8)),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('Password@123'),
            'full_name' => fake()->name(),
            'phone' => '09' . fake()->numerify('########'),
            'role' => User::ROLE_CUSTOMER,
            'balance' => 0,
            'status' => User::STATUS_ACTIVE,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_LOCKED]);
    }
}
