<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(['BTC', 'ETH']),
            'side' => $this->faker->randomElement(['buy', 'sell']),
            'price' => $this->faker->numerify('####.########'),
            'amount' => $this->faker->numerify('##.########'),
            'status' => Order::STATUS_OPEN,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_OPEN,
        ]);
    }

    public function filled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_FILLED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
        ]);
    }
}
