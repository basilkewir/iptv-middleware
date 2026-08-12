<?php

namespace Database\Factories;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionPackageFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Basic', 'Premium', 'VIP', 'Standard', 'Ultimate']);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 50),
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly', 'weekly']),
            'duration_days' => fake()->numberBetween(7, 365),
            'max_connections' => fake()->numberBetween(1, 10),
            'features' => json_encode(['feature1', 'feature2', 'feature3']),
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
