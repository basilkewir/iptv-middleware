<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Stripe', 'PayPal', 'Bitcoin', 'Bank Transfer']);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'gateway' => Str::lower($name),
            'description' => fake()->sentence(),
            'icon' => Str::lower($name),
            'config' => json_encode(['api_key' => 'sk_test_' . Str::random(24)]),
            'supported_currencies' => json_encode(['USD', 'EUR', 'GBP']),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
