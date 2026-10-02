<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 500);
        $fee = fake()->randomFloat(2, 0, 25);
        return [
            'user_id' => null,
            'status' => fake()->randomElement(OrderStatus::cases()),
            'subtotal' => $subtotal,
            'fee' => $fee,
            'total' => $subtotal + $fee,
            'payment_method' => fake()->randomElement(['cash_wallet', 'binance_pay', 'usdt']),
            'payment_ref' => fake()->uuid(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => OrderStatus::Pending,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => OrderStatus::Completed,
        ]);
    }
}
