<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(['BTC', 'ETH']),
            'amount' => $this->faker->numerify('##.########'),
            'locked_amount' => '0.00000000',
        ];
    }

    public function withLockedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_amount' => $this->faker->numerify('#.########'),
        ]);
    }
}
