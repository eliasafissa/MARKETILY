<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 1000);
        $fee = fake()->randomFloat(2, 0, $amount * 0.05);
        return [
            'user_id' => null,
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => $amount,
            'fee' => $fee,
            'status' => fake()->randomElement(TransactionStatus::cases()),
            'method' => fake()->randomElement(['cash_wallet', 'binance_pay', 'usdt']),
            'gateway_ref' => fake()->uuid(),
            'meta' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => TransactionStatus::Pending,
        ]);
    }

    public function deposit(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TransactionType::Deposit,
            'status' => TransactionStatus::Pending,
        ]);
    }

    public function withdrawal(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TransactionType::Withdrawal,
            'status' => TransactionStatus::Pending,
        ]);
    }
}
