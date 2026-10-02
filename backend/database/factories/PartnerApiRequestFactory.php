<?php

namespace Database\Factories;

use App\Models\PartnerApiRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerApiRequestFactory extends Factory
{
    protected $model = PartnerApiRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_name' => fake()->company(),
            'store_url' => fake()->url(),
            'phone' => fake()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
            'status' => 'pending',
            'rejected_reason' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'rejected_reason' => fake()->sentence(),
        ]);
    }
}