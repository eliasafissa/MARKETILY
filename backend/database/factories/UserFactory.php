<?php

namespace Database\Factories;

use App\Enums\VipLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'vip_level' => VipLevel::None,
            'balance' => fake()->randomFloat(2, 0, 5000),
            'banned_at' => null,
        ];
    }

    public function vip1(): static
    {
        return $this->state(fn(array $attributes) => [
            'vip_level' => VipLevel::Vip1,
            'balance' => fake()->randomFloat(2, 100, 3000),
        ]);
    }

    public function vip2(): static
    {
        return $this->state(fn(array $attributes) => [
            'vip_level' => VipLevel::Vip2,
            'balance' => fake()->randomFloat(2, 500, 5000),
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn(array $attributes) => [
            'banned_at' => now(),
        ]);
    }

    public function withBalance(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'balance' => $amount,
        ]);
    }
}
