<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(['auto', 'manual']),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['gamepad', 'message', 'credit-card', 'wallet', 'design', 'monitor', 'server', 'check-circle', 'cpu', 'handshake']),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function manual(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CategoryType::Manual,
        ]);
    }
}
