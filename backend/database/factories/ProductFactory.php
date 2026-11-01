<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        return [
            'category_id' => \App\Models\Category::factory()->create()->id,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(2),
            'price' => fake()->randomFloat(2, 1, 200),
            'stock' => fake()->numberBetween(1, 100),
            'type' => CategoryType::Auto,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CategoryType::Manual,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
